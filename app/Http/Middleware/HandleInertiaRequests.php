<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Str;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Closure: solo se evalúa cuando la respuesta incluye "flash". Así el polling
            // (que pide solo table/tables/menu) nunca consume el mensaje por accidente
            'flash' => fn() => $this->resolveFlash($request),
        ];
    }

    // Convierte ->with('error'|'success'|'info', ...) en un solo aviso para el frontend
    private function resolveFlash(Request $request): ?array
    {
        foreach (['error', 'success', 'info'] as $type) {
            $message = $request->session()->get($type);

            if ($message) {
                return [
                    // Id nuevo en cada respuesta: así el mismo texto repetido
                    // (ej. dos guardados seguidos) vuelve a mostrarse
                    'id'      => (string) Str::uuid(),
                    'type'    => $type,
                    'message' => $message,
                ];
            }
        }

        return null;
    }
}
