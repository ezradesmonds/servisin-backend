<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = [];

    public function serviceCategory() { return $this->belongsTo(ServiceCategory::class); }
    public function serviceProblemType() { return $this->belongsTo(ServiceProblemType::class); }
    public function technician() { return $this->belongsTo(User::class, 'technician_id'); }
}
