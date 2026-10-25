{{-- Browser side of the portal session: the deadline timer, a heartbeat, and the 401 hook for Livewire. --}}
@php($ssoExpiresAt = app(\Postiefs\Sso\SsoSession::class)->expiresAt())
@if ($ssoExpiresAt)
    <script>
        (() => {
            if (window.__ssoGuard) {
                return;
            }

            window.__ssoGuard = true;

            const heartbeatUrl = @js(route('sso.heartbeat'));
            const endedUrl = @js(route('sso.ended'));
            const deadlineMs = {{ (int) $ssoExpiresAt }} * 1000;
            const intervalMs = {{ max(15, (int) config('postiefs-sso.check_interval', 60)) }} * 1000;
            let leaving = false;

            const leave = () => {
                if (! leaving) {
                    leaving = true;
                    window.location.href = endedUrl;
                }
            };

            // The portal's absolute deadline, enforced here without a request. Long waits are re-armed so they stay accurate.
            const arm = () => {
                const remaining = deadlineMs - Date.now();

                if (remaining <= 0) {
                    return leave();
                }

                setTimeout(arm, Math.min(remaining, 60 * 60 * 1000));
            };

            arm();

            // Catches a session the portal ended while the tab sat idle.
            const beat = async () => {
                if (Date.now() >= deadlineMs) {
                    return leave();
                }

                try {
                    const response = await fetch(heartbeatUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    if (response.status === 401) {
                        leave();
                    }
                } catch (_) { /* a network blip is not a sign-out */ }
            };

            setInterval(beat, intervalMs);
            document.addEventListener('visibilitychange', () => { if (! document.hidden) { beat(); } });

            // A Livewire action that hits an ended session.
            const hookLivewire = () => window.Livewire && window.Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 401) {
                        preventDefault();
                        leave();
                    }
                });
            });

            window.Livewire ? hookLivewire() : document.addEventListener('livewire:init', hookLivewire);
        })();
    </script>
@endif
