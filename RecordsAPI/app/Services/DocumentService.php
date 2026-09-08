<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DocumentService
{
    private const PRIVILEGED_ROLES = ['superadmin', 'admin', 'executive', 'alumni'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Document::query()
            ->with(['category', 'creator', 'latestVersion.media'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($filters['category_id'] ?? null, function (Builder $query, int $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when(! $this->currentUserIsPrivileged(), function (Builder $query): void {
                $query->where('is_public', true);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Document
    {
        $document = Document::with([
            'category',
            'creator',
            'latestVersion.media',
            'versions' => fn (HasMany $query): HasMany => $query->with(['uploader', 'media'])
                ->reorder()
                ->orderByRaw('CAST(version_number AS INTEGER) ASC'),
        ])->find($id);

        if ($document && ! $this->isViewAccessible($document)) {
            return null;
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $file): Document
    {
        $document = Document::create(array_merge(['is_public' => false], $data, [
            'created_by' => auth('logto')->id(),
        ]));

        $this->createVersion($document, $file, null);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, array $data): Document
    {
        $document->update($data);

        if (array_key_exists('is_public', $data)) {
            $this->syncDocumentVisibility($document);
        }

        return $document;
    }

    public function delete(Document $document): void
    {
        $document->versions()->each(function (DocumentVersion $version): void {
            $version->clearMediaCollection('file');
        });

        $document->delete();
    }

    public function createVersion(Document $document, UploadedFile $file, ?string $changeSummary): DocumentVersion
    {
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $this->nextVersionNumber($document),
            'change_summary' => $changeSummary,
            'uploaded_by' => auth('logto')->id(),
        ]);

        $version->addMedia($file)->toMediaCollection('file', $this->mediaDiskFor($document));

        return $version;
    }

    public function versions(Document $document): Collection
    {
        return $document->versions()
            ->with(['uploader', 'media'])
            ->reorder()
            ->orderByRaw('CAST(version_number AS INTEGER) ASC')
            ->get();
    }

    public function findVersion(Document $document, int $versionId): ?DocumentVersion
    {
        return $document->versions()
            ->with(['uploader', 'media'])
            ->whereKey($versionId)
            ->first();
    }

    public function findVersionById(int $versionId): ?DocumentVersion
    {
        return DocumentVersion::with(['uploader', 'media'])
            ->whereKey($versionId)
            ->first();
    }

    protected function mediaDiskFor(Document $document): string
    {
        return $document->is_public ? 'public' : 'local';
    }

    protected function syncDocumentVisibility(Document $document): void
    {
        $targetDisk = $this->mediaDiskFor($document);

        $document->versions()->with('media')->get()->each(function (DocumentVersion $version) use ($targetDisk): void {
            $version->getMedia('file')->each(function (Media $media) use ($version, $targetDisk): void {
                if ($media->disk !== $targetDisk) {
                    $media->move($version, 'file', $targetDisk);
                }
            });
        });
    }

    public function isViewAccessible(Document $document): bool
    {
        return $document->is_public || $this->currentUserIsPrivileged();
    }

    protected function currentUserIsPrivileged(): bool
    {
        $user = auth('logto')->user();

        return $user !== null && $user->hasAnyRole(self::PRIVILEGED_ROLES, 'logto');
    }

    protected function nextVersionNumber(Document $document): string
    {
        $max = $document->versions()
            ->reorder()
            ->selectRaw('MAX(CAST(version_number AS INTEGER)) as max')
            ->value('max');

        return (string) ((int) $max + 1);
    }
}
