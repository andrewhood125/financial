<?php

namespace Financial\Outlook;

use Symfony\Component\Yaml\Yaml;
use Carbon\Carbon;

class Outlook
{
    public function __construct($months)
    {
        date_default_timezone_set('UTC');
        $yaml = Yaml::parse(file_get_contents(__DIR__.'/../../Financial.yaml'));
        $this->carbon = Carbon::createFromDate($yaml['date']['year'], $yaml['date']['month'], $yaml['date']['day']);
        $this->months = $months;
        $this->original_income = $yaml['income']['amount'];
        $this->raise = $yaml['income']['apr'];
        $this->expenses = $yaml['expenses'];
        $this->investments = $yaml['investments'];
        $this->loans = $yaml['loans'];
        $this->surplus = 0;

        $this->initInvestments();
        $this->initLoans();
        $this->outlook();
    }

    public function initInvestments()
    {
        foreach ($this->investments as &$investment) {
            $investment['mpr'] = $investment['apr'] / 12;
            $investment['total_interest'] = 0;
        }
    }

    public function initLoans()
    {
        foreach ($this->loans as &$loan) {
            $loan['mpr'] = $loan['apr'] / 12;
            $loan['original_balance'] = $loan['balance'];
            $loan['total_interest'] = 0;
            $loan['total_payments'] = 0;
        }
    }

    public function payExpenses()
    {
        foreach ($this->expenses as $expense) {
            $this->income -= $expense;
        }
    }

    public function calculateInterest($amount, $rate) 
    {
        return $rate / 100 * $amount;
    }

    public function accrueInterest($account)
    {
        $interest = $this->calculateInterest($loan['balance'], $loan['mpr']);

        $account['total_interest'] += $interest;

        $account['balance'] += $interest;
    }

    public function payLoans() 
    {
        foreach ($this->loans as &$loan) {

            if ($loan['balance'] <= 0) {
                continue;
            }

            $this->accrueInterest($loan);

            $this->paymentTo($loan, $loan['payment'] * -1, $loan_history);
        }
    }

    public function payRaise()
    {
        $raise = $this->calculateInterest($this->original_income, $this->raise);

        $this->original_income += $raise;
    }

    public function makeInvestments()
    {
        foreach ($this->investments as &$investment) {

            $this->accrueInterest($investment);

            $this->invest($investment, $investment['contribution'], $investment_history);
        }

    }

    public function outlook()
    {
        for ($i = 0; $i < $this->months; $i++) {

            $this->date = $this->carbon->toDateString();


            if ($i > 0 && $i % 12 == 0) {
                $this->payRaise();
            }

            $this->income = $this->original_income;

            $this->payExpenses();

            $this->payLoans();

            $this->makeInvestments();

            $this->handleSurplusIncome();

            $this->carbon->addMonth();
        }
    }

    public function &worstLoan()
    {
        $apr = 0;
        $worst_loan;
        foreach ($this->loans as &$loan) {
            if ($loan['balance'] > 0 && $loan['apr'] > $apr) {
                $apr = $loan['apr'];
                $worst_loan = &$loan;
            }
        }

        return $worst_loan;
    }

    public function &bestInvestment()
    {
        $apr = 0;
        $best_investment;
        foreach ($this->investments as &$investment) {
            if ($investment['apr'] > $apr) {
                $apr = $investment['apr'];
                $best_investment = &$investment;
            }
        }

        return $best_investment;
    }

    public function paymentTo(&$account, $amount)
    {
        $payment = $amount;

        if (abs($amount) > $account['balance']) {
            $payment = $account['balance'] * -1;
            $account['paid_off'] = $this->date;
        }

        $this->income -= abs($payment);
        $account['balance'] += $payment;
        $account['total_payments'] += abs($payment);

        return abs($amount) - abs($payment);
    }

    public function invest(&$account, $amount)
    {
        $this->income -= $amount;
        $account['balance'] += $amount;
        $account['total_payments'] += $amount;

        return 0;
    }

    public function handleSurplusIncome()
    {
        if ($account = &$this->worstLoan() !== null) {
            $remainder = $this->paymentTo($account, $this->income * -1);
            if ($remainder > 0) {
                $this->handleSurplusIncome($remainder);
            }
        } elseif ($account = &$this->bestInvestment() !== null) {
            $remainder = $this->invest($account, $this->income);
            if ($remainder > 0) {
                $this->handleSurplusIncome($remainder);
            }
        } else {
            $this->surplus += $this->income;
        }
    }

    public function __toString()
    {
        return print_r($this, true);
    }
}
