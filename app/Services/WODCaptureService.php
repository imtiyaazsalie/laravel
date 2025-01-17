<?php

namespace App\Services;

use App\Models\Programme;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WodCapture;

class WODCaptureService
{
    public function store($data)
    {
        $wodCapture = new WodCapture();
        $wodCapture->fill($data);
        $wodCapture->save();

        return $wodCapture;
    }

    public function getWodCapturesByBoxByDateAndProgramme(Tenant $box, \DateTime $date, Programme $programme): array
    {
        return WodCapture::query()
            ->join('wods', 'wods.wod_id', '=', 'wod_captures.wod_id')
            ->where('wods.box_id', '=', $box->getKey())
            ->where('wods.wod_date', $date)
            ->where('wods.programme_id', $programme->getKey())
            ->orderByDesc('wods.wod_date')
            ->get();
    }

    public function getWodCaptureByUserAndDateAndWodProgramme(User $user, \DateTime $date, Programme $programme): ?object
    {
        return WodCapture::query()
            ->join('wods', 'wods.wod_id', '=', 'wod_capture.wod_id')
            ->where('wod_capture.user_id', $user->getKey())
            ->where('wods.wod_date', $date)
            ->where('wods.programme_id', $programme->getKey())
            ->orderByDesc('wods.wod_date')
            ->first();
    }
}
