<?php

declare(strict_types=1);

namespace Lattice\Core\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

final class LatticeStyle
{
    private int $termWidth;

    public function __construct(
        private readonly OutputInterface $output,
    ) {
        $this->termWidth = (new Terminal())->getWidth() ?: 80;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function banner(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<fg=blue>  ' . "\u{2B21}" . ' LatticePHP Framework</>');
        $this->output->writeln('<fg=gray>  Laravel engine ' . "\u{00B7}" . ' NestJS architecture ' . "\u{00B7}" . ' Durable workflows</>');
        $this->output->writeln('');
    }

    public function info(string $message): void
    {
        $this->output->writeln("  <fg=blue>\u{2139}</> {$message}");
    }

    public function success(string $message): void
    {
        $this->output->writeln("  <fg=green>\u{2713}</> {$message}");
    }

    public function error(string $message): void
    {
        $this->output->writeln("  <fg=red>\u{2717}</> {$message}");
    }

    public function warning(string $message): void
    {
        $this->output->writeln("  <fg=yellow>\u{26A0}</> {$message}");
    }

    public function header(string $title): void
    {
        $this->output->writeln('');
        $this->output->writeln("  <fg=white;options=bold>{$title}</>");
        $this->output->writeln('  ' . str_repeat("\u{2500}", min(mb_strlen($title) + 4, $this->termWidth - 4)));
    }

    public function table(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->setStyle('box');
        $table->render();
    }

    public function tree(array $items, int $depth = 0): void
    {
        $keys = array_keys($items);
        $count = count($keys);

        foreach ($keys as $index => $key) {
            $value = $items[$key];
            $prefix = str_repeat('  ', $depth);
            $isLast = ($index === $count - 1);
            $connector = $depth > 0 ? ($isLast ? "\u{2514}\u{2500}\u{2500} " : "\u{251C}\u{2500}\u{2500} ") : '';

            if (is_array($value)) {
                $this->output->writeln("  {$prefix}{$connector}<fg=cyan>{$key}</>");
                $this->tree($value, $depth + 1);
            } else {
                $this->output->writeln("  {$prefix}{$connector}{$value}");
            }
        }
    }

    public function progressBar(int $max): ProgressBar
    {
        $bar = new ProgressBar($this->output, $max);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %message%");
        $bar->setBarCharacter('<fg=green>' . "\u{2588}" . '</>');
        $bar->setEmptyBarCharacter('<fg=gray>' . "\u{2591}" . '</>');
        $bar->setProgressCharacter('<fg=green>' . "\u{2588}" . '</>');
        $bar->setMessage('');
        return $bar;
    }

    public function panel(string $title, string $content): void
    {
        $width = min($this->termWidth - 4, 60);
        $innerWidth = $width - 2;
        $border = str_repeat("\u{2500}", $width);

        $this->output->writeln("  \u{250C}{$border}\u{2510}");
        $this->output->writeln("  \u{2502} <fg=white;options=bold>" . str_pad($title, $innerWidth) . "</>\u{2502}");
        $this->output->writeln("  \u{251C}{$border}\u{2524}");

        foreach (explode("\n", wordwrap($content, $innerWidth - 2)) as $line) {
            $this->output->writeln("  \u{2502} " . str_pad($line, $innerWidth) . "\u{2502}");
        }

        $this->output->writeln("  \u{2514}{$border}\u{2518}");
    }

    public function keyValue(string $key, string $value): void
    {
        $this->output->writeln("  <fg=gray>{$key}:</> <fg=white>{$value}</>");
    }

    public function separator(): void
    {
        $width = min($this->termWidth - 4, 60);
        $this->output->writeln('  ' . str_repeat("\u{2500}", $width));
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->output->writeln('');
        }
    }
}
