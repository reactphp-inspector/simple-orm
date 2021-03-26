<?php

declare(strict_types=1);

namespace ReactInspector\SimpleOrm;

use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Registry;

use function array_map;

final class Metrics
{
    /** @var array<Label> */
    private array $defaultLabels;

    private Registry\Gauges $inflight;
    private Registry\Counters $rows;
    private Registry\Counters $queriesTotal;
    private Registry\Counters $queriesSlow;
    private Registry\Counters $queriesCompleted;
    private Registry\Counters $queriesErrored;
    private Registry\Summaries $responseTimes;
    private Registry\Summaries $completedTimes;

    public function __construct(Registry\Gauges $inflight, Registry\Counters $rows, Registry\Counters $queriesTotal, Registry\Counters $queriesSlow, Registry\Counters $queriesCompleted, Registry\Counters $queriesErrored, Registry\Summaries $responseTimes, Registry\Summaries $completedTimes, Label ...$defaultLabels)
    {
        $this->defaultLabels    = $defaultLabels;
        $this->inflight         = $inflight;
        $this->rows             = $rows;
        $this->queriesTotal     = $queriesTotal;
        $this->queriesSlow      = $queriesSlow;
        $this->queriesCompleted = $queriesCompleted;
        $this->queriesErrored   = $queriesErrored;
        $this->responseTimes    = $responseTimes;
        $this->completedTimes   = $completedTimes;
    }

    public static function create(Registry $registry, Label ...$defaultLabels): self
    {
        $defaultLabelNames = array_map(static fn (Label $label): Label\Name => new Label\Name($label->name()), $defaultLabels);

        return new self(
            $registry->gauge(
                'react_orm_queries_inflight',
                'The number of SQL queries that are currently inflight within the application',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'react_orm_rows',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'react_orm_queries_total',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'react_orm_queries_slow',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'react_orm_queries_completed',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->counter(
                'react_orm_queries_errored',
                'The number of HTTP requests handled by HTTP request method and response status code',
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->summary(
                'react_orm_queries_response_times',
                'The time it took to come to a response by HTTP request method and response status code',
                Factory::defaultQuantiles(),
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            $registry->summary(
                'react_orm_queries_completed_times',
                'The time it took to come to a response by HTTP request method and response status code',
                Factory::defaultQuantiles(),
                new Label\Name('type'),
                new Label\Name('table'),
                ...$defaultLabelNames
            ),
            ...$defaultLabels
        );
    }

    /**
     * @return array<Label>
     */
    public function defaultLabels(): array
    {
        return $this->defaultLabels;
    }

    public function inflight(): Registry\Gauges
    {
        return $this->inflight;
    }

    public function rows(): Registry\Counters
    {
        return $this->rows;
    }

    public function queriesTotal(): Registry\Counters
    {
        return $this->queriesTotal;
    }

    public function queriesSlow(): Registry\Counters
    {
        return $this->queriesSlow;
    }

    public function queriesCompleted(): Registry\Counters
    {
        return $this->queriesCompleted;
    }

    public function queriesErrored(): Registry\Counters
    {
        return $this->queriesErrored;
    }

    public function responseTimes(): Registry\Summaries
    {
        return $this->responseTimes;
    }

    public function completedTimes(): Registry\Summaries
    {
        return $this->completedTimes;
    }
}
