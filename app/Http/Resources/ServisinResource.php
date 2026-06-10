<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServisinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        
        if ($data instanceof \Illuminate\Support\Collection) {
            return $data->map(fn($item) => is_object($item) && !($item instanceof \Illuminate\Database\Eloquent\Model) ? (array) $item : $item)->toArray();
        } elseif (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if ($value instanceof \Illuminate\Support\Collection) {
                    $result[$key] = $value->map(fn($item) => is_object($item) && !($item instanceof \Illuminate\Database\Eloquent\Model) ? (array) $item : $item)->toArray();
                } elseif (is_object($value) && !($value instanceof \Illuminate\Database\Eloquent\Model)) {
                    $result[$key] = (array) $value;
                } else {
                    $result[$key] = $value;
                }
            }
            return $result;
        } elseif ($data instanceof \stdClass) {
            return (array) $data;
        }

        return parent::toArray($request);
    }
}
