<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Http\Resources\ImportRunResource;
use App\Import\DeliveryPlanImporter;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ImportRunResource::collection(ImportRun::query()->with('user')->orderByDesc('id')->limit(50)->get());
    }

    public function store(ImportRequest $request, DeliveryPlanImporter $importer): JsonResponse
    {
        $file = $request->file('file');

        $run = $importer->import($file->getRealPath(), $request->user(), $file->getClientOriginalName());

        return (new ImportRunResource($run->load('issues', 'user')))
            ->response()
            ->setStatusCode($run->status === ImportRun::STATUS_FAILED ? 422 : 201);
    }

    public function show(ImportRun $importRun): ImportRunResource
    {
        return new ImportRunResource($importRun->load('issues', 'user'));
    }
}
