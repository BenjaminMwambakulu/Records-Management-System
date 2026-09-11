<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\StoreDocumentVersionRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentVersionResource;
use App\Http\Responses\APIResponse;
use App\Services\DocumentService;
use App\Support\DocumentFileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    use APIResponse;

    public function __construct(
        protected DocumentService $documentService,
    ) {
        $this->middleware('role:admin|superadmin,logto')->except(
            'index', 'show', 'listVersions', 'showVersion', 'downloadVersionFile',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $documents = $this->documentService->list($request->only(['search', 'category_id', 'status', 'per_page']));

        return $this->success(
            DocumentResource::collection($documents),
            'Documents retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new DocumentResource($document),
            'Document retrieved successfully'
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documentService->create(
            Arr::except($request->validated(), 'file'),
            $request->file('file'),
        );

        return $this->success(
            new DocumentResource($document),
            'Document created successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateDocumentRequest $request, int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->documentService->update($document, $request->validated());

        return $this->success(
            new DocumentResource($document->refresh()),
            'Document updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $this->documentService->delete($document);

        return $this->success(null, 'Document deleted successfully');
    }

    public function storeVersion(StoreDocumentVersionRequest $request, int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $version = $this->documentService->createVersion(
            $document,
            $request->file('file'),
            $request->safe()->input('change_summary'),
        );

        return $this->success(
            new DocumentVersionResource($version),
            'Document version uploaded successfully',
            JsonResponse::HTTP_CREATED,
        );
    }

    public function listVersions(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            DocumentVersionResource::collection($this->documentService->versions($document)),
            'Document versions retrieved successfully'
        );
    }

    public function showVersion(int $id, int $version): JsonResponse
    {
        $document = $this->documentService->find($id);

        if (! $document) {
            return $this->error('Document not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $documentVersion = $this->documentService->findVersion($document, $version);

        if (! $documentVersion) {
            return $this->error('Document version not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success(
            new DocumentVersionResource($documentVersion),
            'Document version retrieved successfully'
        );
    }

    public function downloadVersionFile(Request $request, int $version): Response
    {
        if (! app(DocumentFileUrl::class)->isValid($request, $version)) {
            return $this->error('Forbidden', JsonResponse::HTTP_FORBIDDEN);
        }

        $documentVersion = $this->documentService->findVersionById($version);

        if (! $documentVersion) {
            return $this->error('Document version not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $media = $documentVersion->getFirstMedia('file');

        if (! $media) {
            return $this->error('File not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($media->disk);
        $relativePath = $media->getPathRelativeToRoot();

        if (! $disk->exists($relativePath)) {
            return $this->error('File not found', JsonResponse::HTTP_NOT_FOUND);
        }

        return $disk->response($relativePath);
    }
}
