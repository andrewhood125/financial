<?php namespace Financial\Commands;

use Financial\Outlook\Outlook;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OutlookCommand extends Command {

  /**
   * Configure the command options
   *
   * @return void
   */
  protected function configure() {
    $this->setName('outlook')
      ->setDescription('Take a look at your financial outlook.')
      ->addArgument('months', InputArgument::REQUIRED, 'How far should we look?');
  }

  /**
   * Execute the command.
   *
   * @param  \Symfony\Component\Console\Input\InputInterface  $input
   * @param  \Symfony\Component\Console\Output\OutputInterface  $output
   * @return void
   */
  public function execute(InputInterface $input, OutputInterface $output)
  {
    $months = $input->getArgument('months');
    $outlook = new Outlook($months);
    $output->writeln((string)$outlook);
  }
}
