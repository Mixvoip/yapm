<?php

/**
 * @author bsteffan
 * @since 2025-07-22
 */

namespace App\Controller;

class Pagination
{
    private ?string $previous = null;
    private ?string $next = null;

    /**
     * @return string|null
     */
    public function getPrevious(): ?string
    {
        return $this->previous;
    }

    /**
     * @param  string|null  $previous
     *
     * @return Pagination
     */
    public function setPrevious(?string $previous): Pagination
    {
        $this->previous = $previous;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getNext(): ?string
    {
        return $this->next;
    }

    /**
     * @param  string|null  $next
     *
     * @return Pagination
     */
    public function setNext(?string $next): Pagination
    {
        $this->next = $next;
        return $this;
    }

    /**
     * Create a new Pagination Object.
     *
     * @param  int  $currentPage
     * @param  bool  $hasNextPage
     * @param  string  $baseUrl
     * @param  array  $queryParams
     *
     * @return Pagination
     */
    public static function create(
        int $currentPage,
        bool $hasNextPage,
        string $baseUrl,
        array $queryParams = []
    ): Pagination {
        $previous = null;
        $next = null;

        if ($currentPage > 1) {
            $previousParams = array_merge($queryParams, ["page" => $currentPage - 1]);
            $previous = $baseUrl . "?" . http_build_query($previousParams);
        }

        if ($hasNextPage) {
            $nextParams = array_merge($queryParams, ["page" => $currentPage + 1]);
            $next = $baseUrl . "?" . http_build_query($nextParams);
        }

        return new Pagination()->setPrevious($previous)
                               ->setNext($next);
    }
}
