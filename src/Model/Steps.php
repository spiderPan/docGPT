<?php

namespace Pan\DocGpt\Model;

class Steps implements \IteratorAggregate
{
    private array $history_contexts = [];

    private array $steps = [];


    public function addStep(Step $step): void
    {
        $this->steps[] = $step;
    }

    public function getStep(int $index): Step
    {
        return $this->steps[$index];
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function count(): int
    {
        return count($this->steps);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->steps);
    }

    public function getHistoryContexts(): array
    {
        return $this->history_contexts;
    }

    public function addHistoryContext(string $context): void
    {
        $this->history_contexts[] = $context;
    }

    public function resetHistoryContexts(): void
    {
        $this->history_contexts = [];
    }

}
