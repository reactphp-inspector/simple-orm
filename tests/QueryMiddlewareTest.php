<?php

declare(strict_types=1);

namespace ReactInspector\Tests\SimpleOrm;

use Latitude\QueryBuilder\Engine\PostgresEngine;
use Latitude\QueryBuilder\QueryFactory;
use React\Promise\PromiseInterface;
use ReactInspector\SimpleOrm\Metrics;
use ReactInspector\SimpleOrm\QueryMiddleware;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Printer\Prometheus;

use function ApiClients\Tools\Rx\observableFromArray;
use function ApiClients\Tools\Rx\unwrapObservableFromPromise;
use function array_map;
use function Latitude\QueryBuilder\field;
use function range;
use function React\Promise\resolve;
use function Safe\sleep;

final class QueryMiddlewareTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function collectMetrics(): void
    {
        $registry = Factory::create();

        $queryFactory = new QueryFactory();
        $engine       = new PostgresEngine();
        $middlware    = new QueryMiddleware($engine, 1, Metrics::create($registry, new Label('database', 'test')));
        foreach (range(0, 10) as $sleep) {
            /** @phpstan-ignore-next-line */
            unwrapObservableFromPromise($middlware->query(
                $queryFactory->select('id', 'username')->from('users')->where(field('id')->eq(5))->asExpression(),
                static function () use ($sleep): PromiseInterface {
                    sleep($sleep);

                    return resolve(observableFromArray(array_map(static fn (int $i): array => [$i], range(0, 1337))));
                }
            ))->toArray()->toPromise()->done();
        }

        $metrics = $registry->print(new Prometheus());
        self::assertStringContainsString('react_orm_queries_total_total{database="test",table="users",type="SELECT"} 11', $metrics);
        self::assertStringContainsString('react_orm_rows_total{database="test",table="users",type="SELECT"} 14718', $metrics);
        self::assertStringContainsString('react_orm_queries_slow_total{database="test",table="users",type="SELECT"} 9', $metrics);
        self::assertStringContainsString('react_orm_queries_completed_total{database="test",table="users",type="SELECT"} 11', $metrics);
        self::assertStringContainsString('react_orm_queries_inflight{database="test",table="users",type="SELECT"} 0', $metrics);
        self::assertStringContainsString('react_orm_queries_response_times{quantile="0.1",database="test",table="users",type="SELECT"} 0.5', $metrics);
        self::assertStringContainsString('react_orm_queries_response_times{quantile="0.5",database="test",table="users",type="SELECT"} 4.5', $metrics);
        self::assertStringContainsString('react_orm_queries_response_times{quantile="0.9",database="test",table="users",type="SELECT"} 8.5', $metrics);
        self::assertStringContainsString('react_orm_queries_response_times{quantile="0.99",database="test",table="users",type="SELECT"} 9', $metrics);
        self::assertStringContainsString('react_orm_queries_completed_times{quantile="0.1",database="test",table="users",type="SELECT"} 0.5', $metrics);
        self::assertStringContainsString('react_orm_queries_completed_times{quantile="0.5",database="test",table="users",type="SELECT"} 4.5', $metrics);
        self::assertStringContainsString('react_orm_queries_completed_times{quantile="0.9",database="test",table="users",type="SELECT"} 8.5', $metrics);
        self::assertStringContainsString('react_orm_queries_completed_times{quantile="0.99",database="test",table="users",type="SELECT"} 9', $metrics);
    }
}
