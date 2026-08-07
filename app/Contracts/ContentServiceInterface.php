<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Content;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContentServiceInterface
{
    public function createContent(array $data, string $authorId): Content;

    public function updateContent(string $id, array $data, string $authorId): Content;

    public function publishContent(string $id, string $authorId): Content;

    public function deleteContent(string $id, string $actorId, ?string $reason = null): void;

    public function saveDraft(string $id, string $body): void;

    public function restoreDraft(string $id): ?string;

    public function getContent(string $id, ?string $viewerId = null): ?array;

    public function listContent(array $filters, int $page = 1, int $perPage = 20, ?string $viewerId = null): LengthAwarePaginator;
}
