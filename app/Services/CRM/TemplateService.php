<?php

namespace App\Services\CRM;

use App\Models\Templates;
use App\Traits\Paginatable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TemplateService
{
    use Paginatable;

    public function list()
    {
        return QueryBuilder::for(Templates::class)
            ->allowedFilters([AllowedFilter::exact('type')])
            ->where('box_id', '=', auth()->user()->tenant->getKey())
            ->paginate();
    }

    public function show(Templates $template)
    {
        return $template;
    }

    public function create(array $data)
    {
        $data['box_id'] = auth()->user()->tenant->getKey();
        $data['user_id'] = auth()->user()->getAuthIdentifier();
        $template = new Templates();
        $template->forceFill($data);
        $template->save();

        return $template;
    }

    public function update(Templates $template, array $data)
    {
        $template->forceFill($data);
        $template->save();

        return $template;
    }

    public function delete(Templates $template)
    {
        $template->delete();
    }
}
