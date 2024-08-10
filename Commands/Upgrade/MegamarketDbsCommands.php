<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Megamarket\Orders\Commands\Upgrade;

use BaksDev\Megamarket\Products\Commands\MegamarketPostPriceCommand;
use BaksDev\Megamarket\Products\Commands\MegamarketPostStocksCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:megamarket-orders:dbs',
    description: 'Список всех комманд для обновления «Мegamarket DBS»'
)]
class MegamarketDbsCommands extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success('Обновления «Мegamarket DBS»');

        $helper = $this->getHelper('question');

        $questions[] = 'Все (Enter)';
        $name[] = 0;

        $questions[] = 'Собственная доставка клиенту «DBS Megamarket»';
        $name[] = 'baks:megamarket-orders:dbs-delivery';

        $questions[] = 'Способ оплаты «DBS Megamarket»';
        $name[] = 'baks:megamarket-orders:dbs-payment';

        $questions[] = 'Тип профиля пользователя «DBS Megamarket»';
        $name[] = 'baks:megamarket-orders:dbs-profile';


        $questions[] = 'Выход (Ctrl+C)';
        $name[] = 'quit';

        $question = new ChoiceQuestion(
            'Выберите комманду для выполнения',
            $questions,
            0
        );

        $answer = $helper->ask($input, $output, $question);
        $key = array_search($answer, $question->getChoices(), true);

        if(!empty($name[$key]))
        {
            if($name[$key] === 'quit')
            {
                return Command::SUCCESS;
            }

            $command = ($this->getApplication())->get($name[$key]);
            $command->run($input, $output);
        }
        else
        {
            foreach($name as $item)
            {
                if(empty($item) || $item === 'quit')
                {
                    continue;
                }

                $command = ($this->getApplication())->get($item);
                $command->run($input, $output);
            }
        }

        return Command::SUCCESS;
    }
}
