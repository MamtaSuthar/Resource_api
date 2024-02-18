<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource as BaseJsonResource;

class JsonResource extends BaseJsonResource
{
    public static function collection($resource)
    {
        return tap(new ResourceCollection($resource, static::class), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

    public function additional(array $data)
    {
        $this->additional = \array_merge($this->additional, $data);
        
        return $this;
    }
}

class ResourceCollection extends AnonymousResourceCollection
{
    // extra data on every item in the collection
    protected $extraDataOnEach = [];

    public function setExtraDataOnEach($data = [])
    {
        $this->extraDataOnEach = $data;
    }

    public function toArray($request)
    {
        $resource = $this->resource;

        $request->is_collection = 1;

        return array_merge(
            $resource->toArray(),
            $this->additional
        );
    }
}
