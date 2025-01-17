<?php

namespace App\Services;

use App\Models\WodCaptureComments;

class WODCaptureCommentService
{
    public function store($data)
    {
        $wodCaptureComment = new WodCaptureComments();
        $wodCaptureComment->fill($data);
        $wodCaptureComment->created_by_id = auth()->user()->getAuthIdentifier();
        $wodCaptureComment->save();

        return $wodCaptureComment->loadMissing('user');
    }

    public function update(WodCaptureComments $wodCaptureComment, $data)
    {
        $wodCaptureComment->update($data);

        return $wodCaptureComment->loadMissing('user');
    }
}
