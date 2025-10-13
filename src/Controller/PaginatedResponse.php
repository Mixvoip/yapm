<?php

/**
 * @author bsteffan
 * @since 2025-07-22
 */

namespace App\Controller;

class PaginatedResponse
{
    private array $data;
    private Pagination $pagination;

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param  array  $data
     *
     * @return PaginatedResponse
     */
    public function setData(array $data): PaginatedResponse
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return Pagination
     */
    public function getPagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * @param  Pagination  $pagination
     *
     * @return PaginatedResponse
     */
    public function setPagination(Pagination $pagination): PaginatedResponse
    {
        $this->pagination = $pagination;
        return $this;
    }

    /**
     * Create a paginated response.
     *
     * @param  array  $data
     * @param  int  $currentPage
     * @param  bool  $hasNextPage
     * @param  string  $baseUrl
     * @param  array  $queryParams
     *
     * @return PaginatedResponse
     */
    public static function create(
        array $data,
        int $currentPage,
        bool $hasNextPage,
        string $baseUrl,
        array $queryParams = []
    ): PaginatedResponse {
        $pagination = Pagination::create($currentPage, $hasNextPage, $baseUrl, $queryParams);

        return new PaginatedResponse()->setData($data)
                                      ->setPagination($pagination);
    }
}
