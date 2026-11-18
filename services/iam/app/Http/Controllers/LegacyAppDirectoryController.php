<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Http\Controllers\LegacyAppDirectoryController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class LegacyAppDirectoryController extends Controller
{
    /**
     * List all Legacy Apps, including inactive applications.
     */
    public function index(): JsonResponse
    {
        $apps = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'url',
                'icon',
                'description',
                'is_active',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->map(fn ($app) => $this->formatApp($app))
            ->values()
            ->all();

        return response()->json([
            'data' => $apps,
        ]);
    }

    /**
     * Register a new Legacy App.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('concord_platform.legacy_apps', 'name'),
            ],
            'url' => [
                'required',
                'url',
                'max:2048',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);

        $id = (string) Str::ulid();
        $now = now();

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->insert([
                'id' => $id,
                'name' => $data['name'],
                'url' => $data['url'],
                'icon' => $data['icon'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => true,
                'sort_order' => $data['sort_order'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        Audit::log(
            'iam.legacy_app.created',
            $request->attributes->get('auth')['user_id'],
            $request,
            entityType: 'legacy_app',
            entityId: $id,
            meta: [
                'name' => $data['name'],
                'url' => $data['url'],
            ],
        );

        return response()->json([
            'data' => $this->findApp($id),
        ], 201);
    }

    /**
     * Retrieve a Legacy App.
     */
    public function show(string $legacyApp): JsonResponse
    {
        return response()->json([
            'data' => $this->findApp($legacyApp),
        ]);
    }

    /**
     * Update Legacy App details.
     */
    public function update(
        Request $request,
        string $legacyApp,
    ): JsonResponse {
        $existing = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $legacyApp)
            ->first();

        abort_if($existing === null, 404, 'Legacy App not found.');

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('concord_platform.legacy_apps', 'name')
                    ->ignore($legacyApp, 'id'),
            ],
            'url' => [
                'sometimes',
                'required',
                'url',
                'max:2048',
            ],
            'icon' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);

        if ($data === []) {
            return response()->json([
                'message' => 'Provide at least one field to update.',
            ], 422);
        }

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $legacyApp)
            ->update($data + [
                'updated_at' => now(),
            ]);

        Audit::log(
            'iam.legacy_app.updated',
            $request->attributes->get('auth')['user_id'],
            $request,
            entityType: 'legacy_app',
            entityId: $legacyApp,
            meta: [
                'changed_fields' => array_keys($data),
            ],
        );

        return response()->json([
            'data' => $this->findApp($legacyApp),
        ]);
    }

    /**
     * Activate a Legacy App.
     */
    public function activate(
        Request $request,
        string $legacyApp,
    ): JsonResponse {
        return $this->setActive($request, $legacyApp, true);
    }

    /**
     * Deactivate a Legacy App without deleting its relationships.
     */
    public function deactivate(
        Request $request,
        string $legacyApp,
    ): JsonResponse {
        return $this->setActive($request, $legacyApp, false);
    }

    /**
     * Change the active status of a Legacy App.
     */
    private function setActive(
        Request $request,
        string $legacyApp,
        bool $isActive,
    ): JsonResponse {
        $existing = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $legacyApp)
            ->first();

        abort_if($existing === null, 404, 'Legacy App not found.');

        if ((bool) $existing->is_active !== $isActive) {
            DB::connection('concord_platform')
                ->table('legacy_apps')
                ->where('id', $legacyApp)
                ->update([
                    'is_active' => $isActive,
                    'updated_at' => now(),
                ]);

            Audit::log(
                $isActive
                    ? 'iam.legacy_app.activated'
                    : 'iam.legacy_app.deactivated',
                $request->attributes->get('auth')['user_id'],
                $request,
                entityType: 'legacy_app',
                entityId: $legacyApp,
            );
        }

        return response()->json([
            'data' => $this->findApp($legacyApp),
        ]);
    }

    /**
     * Find a Legacy App by ID.
     */
    private function findApp(string $id): array
    {
        $app = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $id)
            ->first();

        abort_if($app === null, 404, 'Legacy App not found.');

        return $this->formatApp($app);
    }

    /**
     * Format a Legacy App response.
     */
    private function formatApp(object $app): array
    {
        return [
            'id' => $app->id,
            'name' => $app->name,
            'url' => $app->url,
            'icon' => $app->icon,
            'description' => $app->description,
            'is_active' => (bool) $app->is_active,
            'sort_order' => (int) $app->sort_order,
            'created_at' => $app->created_at,
            'updated_at' => $app->updated_at,
        ];
    }
}
