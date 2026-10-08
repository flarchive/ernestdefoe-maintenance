<?php

namespace ErnestDefoe\Maintenance\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MaintenanceEndpointsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-maintenance');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);
    }

    public static function endpoints(): array
    {
        return [
            'migrate' => ['/api/maintenance/migrate'],
            'assets' => ['/api/maintenance/assets'],
        ];
    }

    private function post(string $path, ?int $actor = null): array
    {
        $request = $this->request('POST', $path, $actor ? ['authenticatedAs' => $actor] : []);

        // A guest is refused for the CSRF token before anything else unless it
        // carries one, which would hide whether the controller checks the actor.
        $response = $this->send($actor ? $request : $this->requestWithCsrfToken($request));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function a_guest_cannot_run_maintenance(string $path)
    {
        [$status] = $this->post($path);

        $this->assertSame(403, $status);
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function a_member_cannot_run_maintenance(string $path)
    {
        [$status] = $this->post($path, 2);

        $this->assertSame(403, $status);
    }

    #[Test]
    public function an_admin_runs_the_migrations()
    {
        [$status, $body] = $this->post('/api/maintenance/migrate', 1);

        $this->assertSame(200, $status, json_encode($body));
        $this->assertTrue($body['ok']);
        $this->assertSame(['Nothing to migrate.'], $body['log'], 'Core migrations ran and were already up to date');
    }

    #[Test]
    public function an_admin_publishes_the_assets()
    {
        $this->app();
        $disk = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
        $disk->delete('fonts/fa-solid-900.woff2');

        [$status, $body] = $this->post('/api/maintenance/assets', 1);

        $this->assertSame(200, $status, json_encode($body));
        $this->assertTrue($body['ok']);
        $this->assertContains('Publishing core assets…', $body['log']);
        $this->assertTrue($disk->exists('fonts/fa-solid-900.woff2'), 'Font Awesome webfonts are published');
    }
}
