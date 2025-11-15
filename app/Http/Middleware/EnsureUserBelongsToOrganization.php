<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToOrganization
{
    /**
     * Handle an incoming request.
     *
     * Ensures that users can only access data from their own organization,
     * except for super-admins who have access to all organizations.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no authenticated user, let the auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Super admins can access all organizations
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Ensure user has an organization
        if (!$user->organization_id) {
            return response()->json([
                'message' => 'User must belong to an organization'
            ], 403);
        }

        // Check if request contains organization_id parameter
        if ($request->has('organization_id')) {
            $requestedOrgId = $request->input('organization_id');

            // Verify user has access to this organization
            if ((int) $requestedOrgId !== (int) $user->organization_id) {
                return response()->json([
                    'message' => 'Access denied: You can only access data from your organization',
                    'your_organization_id' => $user->organization_id,
                    'requested_organization_id' => $requestedOrgId
                ], 403);
            }
        }

        // Auto-inject organization_id for certain requests if not provided
        // This helps ensure data isolation even if the client forgets to filter
        if ($request->isMethod('GET') && !$request->has('organization_id')) {
            // For index/list endpoints, automatically add organization filter
            $request->merge(['organization_id' => $user->organization_id]);
        }

        if ($request->isMethod('POST') && !$request->has('organization_id')) {
            // For create endpoints, automatically set organization
            $request->merge(['organization_id' => $user->organization_id]);
        }

        return $next($request);
    }
}
