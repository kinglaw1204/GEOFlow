<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminWeb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiPromptsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_content_prompts_are_visible(): void
    {
        $admin = Admin::query()->create([
            'username' => 'ai_prompt_admin',
            'password' => 'secret-123',
            'email' => 'ai-prompt-admin@example.com',
            'display_name' => 'AI Prompt Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-prompts'))
            ->assertOk()
            ->assertSee('GEO营销学·信任型正文生成')
            ->assertSee('GEO榜单型正文生成')
            ->assertSee('GEO Marketing · Trust-Based Article Generation (English)')
            ->assertSee('GEO Ranking-Style Article Generation (English)');
    }

    public function test_prompt_actions_keep_the_configured_proxy_prefix(): void
    {
        config(['app.url' => 'https://www.nanshanlin.com/manage/geoflow/proxy']);

        $admin = Admin::query()->create([
            'username' => 'proxy_prompt_admin',
            'password' => 'secret-123',
            'email' => 'proxy-prompt-admin@example.com',
            'display_name' => 'Proxy Prompt Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.ai-prompts'));
        $updatePath = str_replace('/', '\\/', AdminWeb::routePath('admin.ai-prompts.update', ['promptId' => '__ID__']));
        $deletePath = str_replace('/', '\\/', AdminWeb::routePath('admin.ai-prompts.delete', ['promptId' => '__ID__']));

        $response->assertOk()
            ->assertSee(AdminWeb::routePath('admin.ai-prompts.store'), false)
            ->assertSee($updatePath, false)
            ->assertSee($deletePath, false);
    }
}
