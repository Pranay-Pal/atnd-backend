<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ProvidesFormattedBranding
{
    /**
     * Dynamically format logo_url for external consumption based on incoming request host.
     */
    private function formatSettings(array|null $settings): array
    {
        $settings = $settings ?? [];
        if (isset($settings['logo_url'])) {
            $url = $settings['logo_url'];
            
            // 1. If it's a relative API path
            if (str_starts_with($url, '/api/branding-image?path=')) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $url;
            } 
            // 2. If it's an absolute path but might have the wrong domain (from another server)
            // Replace the domain part with the current request domain for reliability
            elseif (preg_match('/^https?:\/\/[^\/]+(\/api\/branding-image\?path=.*)$/', $url, $matches)) {
                $settings['logo_url'] = request()->getSchemeAndHttpHost() . $matches[1];
            }
            // 3. Backward compatibility: if it's still using /storage/ (e.g. from local DB)
            elseif (str_starts_with($url, '/storage/')) {
                 $settings['logo_url'] = request()->getSchemeAndHttpHost() . $url;
            }
        }
        return $settings;
    }
}
