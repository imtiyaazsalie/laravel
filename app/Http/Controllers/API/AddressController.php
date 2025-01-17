<?php

namespace App\Http\Controllers\API;

use App\Enums\AddressType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Address\CreateAddressRequest;
use App\Http\Requests\Address\DeleteAddressRequest;
use App\Http\Requests\Address\ReadAddressRequest;
use App\Http\Requests\Address\SearchAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function __construct(protected AddressService $address)
    {
    }

    public function search(SearchAddressRequest $request): array
    {
        return $this->address->search($request->input('address'));
    }

    public function show(ReadAddressRequest $request, Address $address): AddressResource
    {
        return new AddressResource($address);
    }

    public function store(AddressType $type, int $id, CreateAddressRequest $request): AddressResource
    {
        $model = $type->getModelClass()::findOrFail($id);

        return new AddressResource($model->addresses()->create($request->validated()));
    }

    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        $address->update($request->validated());

        return new AddressResource($address);
    }

    public function destroy(DeleteAddressRequest $request, Address $address): Response
    {
        $address->delete();

        return response()->noContent();
    }
}
