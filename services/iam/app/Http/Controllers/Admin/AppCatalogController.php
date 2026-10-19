<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppCatalogController extends Controller
{
    /** Return the application catalog with its current groups and grantable Blocks. */
    public function index(): JsonResponse
    {
        $apps = DB::table('apps')->orderBy('sort_order')->orderBy('name')->get();

        $payload = $apps->map(function ($app) {
            $groups = DB::table('app_groups')
                ->where('app_id', $app->id)
                ->orderBy('sort_order')->orderBy('name')->get();

            return [
                'id' => $app->id,
                'key' => $app->key,
                'name' => $app->name,
                'sort_order' => $app->sort_order,
                'groups' => $groups->map(function ($group) {
                    $blocks = DB::table('blocks')
                        ->where('group_id', $group->id)
                        ->orderBy('sort_order')->orderBy('name')->get();

                    return [
                        'id' => $group->id,
                        'key' => $group->key,
                        'name' => $group->name,
                        'sort_order' => $group->sort_order,
                        'blocks' => $blocks->map(fn ($block) => [
                            'id' => $block->id,
                            'key' => $block->key,
                            'name' => $block->name,
                            'description' => $block->description,
                            'is_view' => (bool) $block->is_view,
                            'sort_order' => $block->sort_order,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json(['data' => $payload]);
    }

    /** Create an application in the platform-wide catalog. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]*$/', 'unique:apps,key'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $id = (string) Str::ulid();
        $now = now();
        DB::table('apps')->insert([
            'id' => $id,
            'key' => $data['key'],
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Audit::log(
            'iam.app.created',
            $request->attributes->get('auth')['user_id'],
            $request,
            entityType: 'app',
            entityId: $id,
            meta: ['key' => $data['key'], 'name' => $data['name']],
        );

        return response()->json(['data' => $this->findApp($id)], 201);
    }

    /** Update mutable application metadata; keys remain stable for existing references. */
    public function update(Request $request, string $appId): JsonResponse
    {
        $existing = DB::table('apps')->where('id', $appId)->first();
        abort_if(! $existing, 404, 'Application not found.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ]);

        if ($data === []) {
            return response()->json(['message' => 'Provide at least one field to update.'], 422);
        }

        DB::table('apps')->where('id', $appId)->update($data + ['updated_at' => now()]);

        Audit::log(
            'iam.app.updated',
            $request->attributes->get('auth')['user_id'],
            $request,
            entityType: 'app',
            entityId: $appId,
            meta: ['changed_fields' => array_keys($data)],
        );

        return response()->json(['data' => $this->findApp($appId)]);
    }

    private function findApp(string $appId): array
    {
        $app = DB::table('apps')->where('id', $appId)->first();

        return [
            'id' => $app->id,
            'key' => $app->key,
            'name' => $app->name,
            'sort_order' => $app->sort_order,
        ];
    }
}
