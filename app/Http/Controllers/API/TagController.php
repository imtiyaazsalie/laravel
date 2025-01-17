<?php

namespace App\Http\Controllers\API;

use App\Enums\TagType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\CreateTagRequest;
use App\Http\Requests\Tags\DeleteTagRequest;
use App\Http\Requests\Tags\ListTagsRequest;
use App\Http\Requests\Tags\ListTagTypesRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagsService;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TagController extends Controller
{
    public function list(ListTagsRequest $request)
    {
        return TagResource::collection(
            QueryBuilder::for(Tag::class)->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::scope('tenant_id', 'ownedByGym'),
                AllowedFilter::scope('location_id', 'ownedByLocation'),
            ])
                ->_paginate()
        );
    }

    public function types(ListTagTypesRequest $request)
    {
        return response()->json(TagType::asArray(), Response::HTTP_OK);
    }

    public function create(CreateTagRequest $request)
    {
        $type = TagType::from($request->type);

        // Check if global tag exists
        abort_if(
            boolean: Tag::query()->global()->where('name', strtolower($request->name))->exists(),
            code: 400,
            message: 'Global tag already exists.'
        );

        [
            'ownerModelId' => $ownerModelId,
            'ownerModel' => $ownerModel
        ] = (new TagsService())->getOwnerIdAndModel($type, $request->tenant_id, $request->location_id);

        // Check if box or box facility tag already exists
        abort_if(
            boolean: Tag::query()
                ->where('name', strtolower($request->name))
                ->where('type', $request->type)
                ->where('owner_model_id', $ownerModelId)
                ->where('owner_model', $ownerModel)
                ->exists(),
            code: 400,
            message: 'Tag already exists.'
        );

        $tag = Tag::create([
            'name' => $request->name,
            'type' => $type,
            'owner_model_id' => $ownerModelId,
            'owner_model' => $ownerModel,
        ]);

        return new TagResource($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        abort_if(
            boolean: $tag->isGlobal(),
            code: 400,
            message: 'Global tag cannot be edited.'
        );

        // Check if global tag exists
        abort_if(
            boolean: Tag::query()->global()->where('name', strtolower($request->name))->exists(),
            code: 400,
            message: 'Global tag already exists.'
        );

        // Check if box or box facility tag already exists
        abort_if(
            boolean: Tag::query()
                ->where('name', strtolower($request->name))
                ->where('type', $tag->type)
                ->where('owner_model_id', $tag->owner_model_id)
                ->where('owner_model', $tag->owner_model)
                ->exists(),
            code: 400,
            message: 'Tag already exists.'
        );

        $tag->name = $request->name;
        $tag->save();

        return new TagResource($tag);
    }

    public function delete(DeleteTagRequest $request, Tag $tag)
    {
        abort_if(
            boolean: $tag->isGlobal(),
            code: 400,
            message: 'Global tag cannot be deleted.'
        );

        $tag->delete();

        return response()->noContent();
    }
}
