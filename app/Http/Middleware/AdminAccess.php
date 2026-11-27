<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $role = $request->attributes->get('admin')?->role;
        if ($role === 'admin') {
            return $next($request);
        }
        // Staff can read dashboard data and handle enquiries, but cannot
        // publish content, manage accounts/settings, upload, or delete records.
        $resource = $request->route('resource');
        $read = $request->isMethod('GET') && (
            in_array($resource, ['categories', 'courses', 'branches', 'testimonials', 'features', 'enquiries', 'blog', 'gallery', 'awards', 'admins'], true)
            || $request->is('api/admin/enquiries/*/notes')
        );
        $enquiry = ($request->isMethod('PUT') && $resource === 'enquiries')
            || ($request->isMethod('POST') && $request->is('api/admin/enquiries/*/notes'));
        if ($role === 'staff' && ($read || $enquiry)) {
            return $next($request);
        }
        return response()->json(['status' => 0, 'message' => 'Forbidden'], 403);
    }
}
