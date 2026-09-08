<?php

use App\Models\DocumentVersion;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('event cover media is stored under its model and collection directory', function () {
    Storage::fake('public');

    $event = Event::factory()->create();
    $event->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->toMediaCollection('cover');

    $media = $event->getFirstMedia('cover');

    expect($media)->not->toBeNull();
    Storage::disk('public')->assertExists("events/covers/{$media->id}/cover.jpg");
    expect($event->getFirstMediaUrl('cover'))
        ->toContain("/storage/events/covers/{$media->id}/cover.jpg");
});

test('document version file media is stored under its model and collection directory', function () {
    Storage::fake('public');

    $version = DocumentVersion::factory()->create();
    $version->addMedia(UploadedFile::fake()->create('guidelines.pdf', 100))
        ->toMediaCollection('file');

    $media = $version->getFirstMedia('file');

    expect($media)->not->toBeNull();
    Storage::disk('public')->assertExists("document-versions/files/{$media->id}/guidelines.pdf");
    expect($version->getFirstMediaUrl('file'))
        ->toContain("/storage/document-versions/files/{$media->id}/guidelines.pdf");
});
