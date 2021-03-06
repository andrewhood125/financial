<?php

namespace Financial\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
  /**
   * Configure the command.
   */
  protected function configure()
  {
      $this->setName('init')
      ->setDescription('Initialize financial with financial.yaml');
  }

  /**
   * Initialize financial.
   *
   * @param  \Symfony\Component\Console\Input\InputInterface  $input
   * @param  \Symfony\Component\Console\Output\OutputInterface  $output
   */
  public function execute(InputInterface $input, OutputInterface $output)
  {
      copy(__DIR__.'/../stubs/Financial.yaml', __DIR__.'/../../Financial.yaml');

      $output->writeln('Financial.yaml created!');
  }
}
