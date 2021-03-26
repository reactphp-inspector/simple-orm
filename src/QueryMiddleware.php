<?php

declare(strict_types=1);

namespace ReactInspector\SimpleOrm;

use Latitude\QueryBuilder\EngineInterface;
use Latitude\QueryBuilder\ExpressionInterface;
use marcocesarato\sqlparser\LightSQLParser;
use React\Promise\PromiseInterface;
use Rx\Observable;
use Rx\Subject\Subject;
use Throwable;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Registry;
use WyriHaximus\React\SimpleORM\MiddlewareInterface;

use function hrtime;
use function React\Promise\resolve;

final class QueryMiddleware implements MiddlewareInterface
{
    private EngineInterface $engine;
    private int $slowQueryTime;

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

    public function __construct(EngineInterface $engine, int $slowQueryTime, Metrics $metrics)
    {
        $this->engine           = $engine;
        $this->slowQueryTime    = $slowQueryTime;
        $this->defaultLabels    = $metrics->defaultLabels();
        $this->inflight         = $metrics->inflight();
        $this->rows             = $metrics->rows();
        $this->queriesTotal     = $metrics->queriesTotal();
        $this->queriesSlow      = $metrics->queriesSlow();
        $this->queriesCompleted = $metrics->queriesCompleted();
        $this->queriesErrored   = $metrics->queriesErrored();
        $this->responseTimes    = $metrics->responseTimes();
        $this->completedTimes   = $metrics->completedTimes();
    }

    public function query(ExpressionInterface $query, callable $next): PromiseInterface
    {
        $parsedQuery = new LightSQLParser($query->sql($this->engine));
        $labels      = $this->defaultLabels;
        $labels[]    = new Label('type', $parsedQuery->getMethod());
        $labels[]    = new Label('table', $parsedQuery->getTable());
        $this->queriesTotal->counter(...$labels)->incr();
        $gauge = $this->inflight->gauge(...$labels);
        $gauge->incr();
        $startTime = hrtime()[0];

        return resolve($next($query))->then(function (Observable $observable) use ($startTime, $gauge, $labels): PromiseInterface {
            return resolve(Observable::defer(function () use ($observable, $startTime, $labels, $gauge): Subject {
                $handledInitialRow = false;
                $subject           = new Subject();
                $observable->subscribe(
                    function (array $row) use ($subject, $startTime, &$handledInitialRow, $labels, $gauge): void {
                        $subject->onNext($row);
                        $this->rows->counter(...$labels)->incr();

                        if ($handledInitialRow === true) {
                            return;
                        }

                        $gauge->dcr();
                        $this->responseTimes->summary(...$labels)->observe(hrtime()[0] - $startTime);

                        if (hrtime()[0] - $startTime > $this->slowQueryTime) {
                            $this->queriesSlow->counter(...$labels)->incr();
                        }

                        $handledInitialRow = true;
                    },
                    function (Throwable $throwable) use ($startTime, $subject, &$handledInitialRow, $labels, $gauge): void {
                        $subject->onError($throwable);

                        $gauge->dcr();
                        $this->queriesErrored->counter(...$labels)->incr();
                        $this->completedTimes->summary(...$labels)->observe(hrtime()[0] - $startTime);

                        if ($handledInitialRow === true) {
                            return;
                        }

                        if (hrtime()[0] - $startTime <= $this->slowQueryTime) {
                            return;
                        }

                        $this->queriesSlow->counter(...$labels)->incr();
                    },
                    function () use ($subject, &$handledInitialRow, $labels, $gauge, $startTime): void {
                        $subject->onCompleted();

                        $this->queriesCompleted->counter(...$labels)->incr();
                        $this->completedTimes->summary(...$labels)->observe(hrtime()[0] - $startTime);

                        if ($handledInitialRow === true) {
                            return;
                        }

                        $gauge->dcr();
                    },
                );

                return $subject;
            }));
        });
    }
}
