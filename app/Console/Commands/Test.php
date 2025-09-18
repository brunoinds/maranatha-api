<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use DateTime;
use App\Support\Exchange\Currencies\PYG;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $result = PYG::convertFromDollar(DateTime::createFromFormat('Y-m-d', '2025-09-18'), 1);
        $this->info('1USD = ' . $result . 'PYG at ' . DateTime::createFromFormat('Y-m-d', '2025-09-18')->format('Y-m-d'));
    }
}
