<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use Symfony\Component\HttpClient\Response\JsonMockResponse;

class SearchResultMockResponse extends JsonMockResponse
{
    public function __construct(
        private array $hits = [],
        private string $query = '',
        private int $estimatedTotalHits = 0,
        private int $processingTimeMs = 0,
        private int $offset = 0,
        private int $limit = 0,
    )
    {
        parent::__construct([
            'hits' => $this->hits,
            'processingTimeMs' => $this->processingTimeMs,
            'estimatedTotalHits' => $this->estimatedTotalHits,
            'query' => $this->query,
            'offset' => $this->offset,
            'limit' => $this->limit,
        ]);
    }
}
