<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\IntentionRequest;
use App\Services\ParentPortal\Records;

class IntentionController extends Controller
{
    public function __construct(private Records $records) {}

    public function index(string $student)
    {
        return ['data' => $this->records->student($student)->intentions()->orderByRaw("CASE WHEN level = 'first' THEN 0 ELSE 1 END")->orderBy('created_at')->get()];
    }

    public function store(IntentionRequest $r, string $student)
    {
        return response()->json(['data' => $this->records->saveIntention($this->records->student($student), $r->validated())], 201);
    }

    public function update(IntentionRequest $r, string $id)
    {
        $i = $this->records->intention($id);

        return ['data' => $this->records->saveIntention($i->student, $r->validated(), $i)];
    }

    public function destroy(string $id)
    {
        $this->records->intention($id)->delete();

        return response()->noContent();
    }
}
