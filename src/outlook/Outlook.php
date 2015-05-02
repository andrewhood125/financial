<?php

namespace Financial\Outlook;

use Symfony\Component\Yaml\Yaml;
use Carbon\Carbon;

class Outlook
{
    public $surplus = 0;
    public $history = [];

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

    public function outlook()
    {
        for ($i = 0; $i < $this->months; $i++) {
            $this->date = $this->carbon->toDateString();
            $this_day_in_history = [];

            // Pay Raise Once A Year
            if ($i > 0 && $i % 12 == 0) {
                $this->original_income += $this->original_income * $this->raise / 100;
                $this_day_in_history['pay_raise'] = $this->original_income;
            }

            $this->income = $this->original_income;

            // pay expenses
            foreach ($this->expenses as $expense) {
                $this->income -= $expense;
            }

            // pay loans
            foreach ($this->loans as &$loan) {
                $loan_history = [
                    'payment' => 0,
                ];

                if ($loan['balance'] <= 0) {
                    continue;
                }

                // calculate interest
                $interest = $loan['mpr'] / 100 * $loan['balance'];
                $loan_history['interest'] = $interest;
                $loan['total_interest'] += $interest;

                // compound interest
                $loan['balance'] += $interest;
                $loan_history['balance'] = $loan['balance'];

                $this->paymentTo($loan, $loan['payment'] * -1, $loan_history);

                $this_day_in_history[$loan['name']] = $loan_history;
            }

            // make investments
            foreach ($this->investments as &$investment) {
                $investment_history = [];

                // calculate interest
                $interest = $investment['mpr'] / 100 * $investment['balance'];
                $investment['total_interest'] += $interest;
                $investment_history['interest'] = $interest;

                // compound interest
                $investment['balance'] += $interest;
                $investment_history['balance'] = $investment['balance'];

                $this->invest($investment, $investment['contribution'], $investment_history);

                $this_day_in_history[$investment['name']] = $investment_history;
            }

            $this_day_in_history['surplus'] = [
                'balance' => $this->income 
            ];

            $this->history[$this->date] = $this_day_in_history;

            $this->handleSurplusIncome($this->income);

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

    public function paymentTo(&$account, $amount, &$history)
    {
        $payment = $amount;

        if (abs($amount) > $account['balance']) {
            $payment = $account['balance'] * -1;
            $account['paid_off'] = $this->date;
        }

        $this->income -= abs($payment);
        $account['balance'] += $payment;
        $account['total_payments'] += abs($payment);
        $history['balance'] = $account['balance'];
        $history['payment'] += abs($payment);

        return abs($amount) - abs($payment);
    }

    public function invest(&$account, $amount, &$history)
    {
        $this->income -= $amount;
        $account['balance'] += $amount;
        $account['total_payments'] += $amount;
        $history['balance'] = $account['balance'];
        $history['payment'] += $amount;

        return 0;
    }

    public function handleSurplusIncome($surplus_income)
    {
        if ($account = &$this->worstLoan() !== null) {
            $remainder = $this->paymentTo($account, $surplus_income * -1, $this->history[$this->date][$account['name']]);
            if ($remainder > 0) {
                $this->handleSurplusIncome($remainder);
            }
        } elseif ($account = &$this->bestInvestment() !== null) {
            $remainder = $this->invest($account, $surplus_income, $this->history[$this->date][$account['name']]);
            if ($remainder > 0) {
                $this->handleSurplusIncome($remainder);
            }
        } else {
            $this->surplus += $surplus_income;
        }
    }

    public function __toString()
    {
        $string = '';

        // Add history
        foreach($this->history as $date => $month) {
            $string .= $date . "\n";
            foreach($month as $name => $account) {
                $string .= "\n\t" . $name . ":\n\t\t";
                foreach($account as $key => $value) {
                    $string .= sprintf("\t%s: $%'#6.2f", $key, $value);
                }
                $string .= "\n";
            }

            $string .= "----------------------------------------------------------------------------------------------------------------------\n\n";
        }

        // Add income
        $string .= "Income:\t\t".$this->original_income."\n";

        // Add surplus
        $string .= "Surplus:\t".$this->surplus."\n";

        // Add loans
        $string .= "Loans:\n";
        foreach ($this->loans as $loan) {
            $string .= "\n\tname:\t\t".$loan['name']."\n";
            $string .= "\tapr:\t\t".$loan['apr']."\n";
            $string .= "\ttotal payments:\t".$loan['total_payments']."\n";
            $string .= "\ttotal interest:\t".$loan['total_interest']."\n";

            // loan paid off
            if (array_key_exists('paid_off', $loan)) {
                $string .= "\tstatus:\t\tPaid off on ".$loan['paid_off']."\n";
            } else {
                $string .= "\tbalance:\t".$loan['balance']."\n";
            }
        }

        // Add investments
        $string .= "\nInvestments:\n";
        foreach ($this->investments as $investment) {
            $string .= "\n\tname:\t\t".$investment['name']."\n";
            $string .= "\tapr:\t\t".$investment['apr']."\n";
            $string .= "\tbalance:\t".$investment['balance']."\n";
        }

        return $string;
        //return print_r($this->history, true);
    }
}
