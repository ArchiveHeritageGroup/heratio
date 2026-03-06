<?php

namespace AhgCore\Pagination;

class SimplePager
{
    protected array $results = [];
    protected int $total = 0;
    protected int $page = 1;
    protected int $limit = 30;

    public function __construct(array $data)
    {
        $this->results = $data['hits'] ?? [];
        $this->total = (int) ($data['total'] ?? 0);
        $this->page = max(1, (int) ($data['page'] ?? 1));
        $this->limit = max(1, (int) ($data['limit'] ?? 30));
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getNbResults(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getMaxPerPage(): int
    {
        return $this->limit;
    }

    public function getLastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->limit));
    }

    public function haveToPaginate(): bool
    {
        return $this->total > $this->limit;
    }

    public function getFirstPage(): int
    {
        return 1;
    }

    public function getNextPage(): int
    {
        return min($this->page + 1, $this->getLastPage());
    }

    public function getPreviousPage(): int
    {
        return max($this->page - 1, 1);
    }

    public function getLinks(int $nbLinks = 5): array
    {
        $lastPage = $this->getLastPage();
        $start = max(1, $this->page - (int) floor($nbLinks / 2));
        $end = min($lastPage, $start + $nbLinks - 1);
        $start = max(1, $end - $nbLinks + 1);

        return range($start, $end);
    }
}
