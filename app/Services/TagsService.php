<?php

namespace App\Services;

use App\Enums\TagType;
use App\Models\Location;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TagsService
{
    public function checkTagIdOwnership(array $tagIds, string|int $boxId, string|int $locationId): bool
    {
        $tagCount = Tag::query()
            ->ownedBy($boxId, $locationId)
            ->whereIn('id', $tagIds)
            ->count();

        return $tagCount === count($tagIds);
    }

    /**
     * Sync tags for a morphable model
     */
    public function sync(?array $tagIds, Model $model, ?string $userId = null): bool
    {
        if (is_null($tagIds)) {
            $tagIds = [];
        }

        $tagIds = array_unique($tagIds);

        $morphableModel = $model->getTable();

        $taggables = Taggable::query()
            ->whereMorphableId($model->getKey())
            ->whereMorphableModel($morphableModel)
            ->get();

        //filter out existing tags
        $inserts = array_filter($tagIds, fn ($tagId) => ! in_array($tagId, $taggables->pluck('tag_id')->toArray()));

        //map inserts
        $inserts = array_map(function ($tagId) use ($userId, $model, $morphableModel) {
            return [
                'tag_id' => $tagId,
                'morphable_id' => $model->getKey(),
                'morphable_model' => $morphableModel,
                'created_by_id' => $userId,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }, $inserts);

        // Get tags to delete
        $deletes = $taggables->whereNotIn('tag_id', $tagIds)->modelKeys();

        if (! empty($inserts)) {
            Taggable::insert($inserts);
        }

        if (! empty($deletes)) {
            Taggable::whereIn('id', $deletes)->delete();
        }

        return true;
    }

    public function getTags(int $boxId, ?string $type = null, ?int $ownerModelId = null, ?string $ownerModel = null, ?array $tagIds = null): Collection|array
    {
        $qb = Tag::query()
            ->withoutGlobalScopes()
            ->from('tags', 't');

        if ($ownerModelId && $ownerModel) {
            $qb
                ->where(function (Builder $query) use ($ownerModelId) {
                    $query->whereNull('t.owner_model_id')
                        ->orWhere('t.owner_model_id', $ownerModelId);
                })
                ->where(function (Builder $query) use ($ownerModel) {
                    $query->whereNull('t.owner_model')
                        ->orWhere('t.owner_model', $ownerModel);
                });
        } else {
            $in = Location::query()
                ->from('box_facility', 'bf')
                ->select('bf.box_facility_id')
                ->where('bf.box_id', $boxId)
                ->where('bf.is_active', true)
                ->get();

            $qb->where('t.owner_model', 'box_facility')
                ->whereIn('t.owner_model_id', $in)
                ->orWhere(function ($query) use ($boxId) {
                    $query
                        ->where('t.owner_model', 'boxes')
                        ->where('t.owner_model_id', $boxId)
                        ->orWhereNull('t.owner_model_id')
                        ->orWhereNull('t.owner_model');
                });
        }

        if ($type) {
            $qb->where('t.type', $type);
        }

        if (is_array($tagIds)) {
            $qb->whereIn('t.id', $tagIds);
        }

        return $qb->get();
    }

    public function getOwnerIdAndModel(TagType $type, ?int $tenantId = null, ?int $locationId = null): array
    {
        abort_if(
            boolean: ! $tenantId && ! $locationId,
            code: 400,
            message: 'Make sure that tenant ID or location ID is set.'
        );

        abort_if(
            boolean: in_array($type, [TagType::PACKAGE, TagType::PAYMENT]) && ! $tenantId,
            code: 400,
            message: 'Tenant ID is a required field.'
        );

        abort_if(
            boolean: in_array($type, [TagType::LOCATION]) && ! $locationId,
            code: 400,
            message: 'Location ID is a required field.'
        );

        switch ($type) {
            case TagType::PACKAGE:
            case TagType::PAYMENT:
                $ownerModelId = $tenantId;
                $ownerModel = (new Tenant())->getTable();
                break;
            case TagType::LOCATION:
                $ownerModelId = $locationId;
                $ownerModel = (new Location())->getTable();
                break;
            default:
                abort(400, 'Type is not supported.');
        }

        return [
            'ownerModelId' => $ownerModelId,
            'ownerModel' => $ownerModel,
        ];
    }
}
