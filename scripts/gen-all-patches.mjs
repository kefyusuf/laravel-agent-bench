#!/usr/bin/env node
/**
 * Generates every solution and distractor patch for all scenarios.
 * Each patch: materialize workspace -> apply edits (find/replace, each exactly once)
 * -> optionally add new files -> `git diff` -> write into scenarios/<id>/patches/.
 */
import { readFileSync, writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadConfig, cfgPath } from '../src/config.mjs';
import { discoverScenarios } from '../src/scenarios.mjs';
import { materializeWorkspace } from '../src/materialize.mjs';

/** Patch definitions: scenarioId -> [{name, edits: [{file, find, replace}], newFiles: [{rel, content}]}] */
const PATCHES = {
  'webhook-payout-idempotency': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/PaymentWebhookController.php',
        find: '                ProcessPayout::dispatch($order->id);',
        replace: '                ProcessPayout::dispatch($order->id)->afterCommit();',
      }],
    },
    {
      name: 'distractor-unique-job.patch',
      edits: [
        {
          file: 'app/Jobs/ProcessPayout.php',
          find: 'use Illuminate\\Contracts\\Queue\\ShouldQueue;\nuse Illuminate\\Foundation\\Queue\\Queueable;',
          replace: 'use Illuminate\\Contracts\\Queue\\ShouldBeUnique;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nuse Illuminate\\Foundation\\Queue\\Queueable;',
        },
        {
          file: 'app/Jobs/ProcessPayout.php',
          find: 'class ProcessPayout implements ShouldQueue',
          replace: 'class ProcessPayout implements ShouldQueue, ShouldBeUnique',
        },
        {
          file: 'app/Jobs/ProcessPayout.php',
          find: '    public function backoff(): array',
          replace: '    public function uniqueId(): string\n    {\n        return (string) $this->orderId;\n    }\n\n    public function backoff(): array',
        },
      ],
    },
    {
      name: 'distractor-retry-tuning.patch',
      edits: [
        {
          file: 'app/Jobs/ProcessPayout.php',
          find: '    public int $tries = 3;',
          replace: '    public int $tries = 5;',
        },
        {
          file: 'app/Jobs/ProcessPayout.php',
          find: '        return [10, 60];',
          replace: '        return [60, 300, 900];',
        },
      ],
    },
  ],

  'retry-after-vs-timeout': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'config/queue.php',
        find: "'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 30),",
        replace: "'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 300),",
      }],
    },
    {
      name: 'distractor-raise-timeout.patch',
      edits: [{
        file: 'app/Jobs/TranscodeVideo.php',
        find: 'public int $timeout = 120;',
        replace: 'public int $timeout = 240;',
      }],
    },
    {
      name: 'distractor-unbounded-timeout.patch',
      edits: [{
        file: 'app/Jobs/TranscodeVideo.php',
        find: 'public int $timeout = 120;',
        replace: 'public int $timeout = 0;',
      }],
    },
  ],

  'unique-job-tenant-key': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Jobs/GenerateTenantReport.php',
        find: "        return $this->report.'-report';",
        replace: "        return $this->report.'-report:'.$this->tenant->id;",
      }],
    },
    {
      name: 'distractor-longer-ttl.patch',
      edits: [{
        file: 'app/Jobs/GenerateTenantReport.php',
        find: '        return 3600;',
        replace: '        return 86400;',
      }],
    },
    {
      name: 'distractor-drop-uniqueness.patch',
      edits: [
        {
          file: 'app/Jobs/GenerateTenantReport.php',
          find: 'use Illuminate\\Contracts\\Queue\\ShouldBeUnique;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;',
          replace: 'use Illuminate\\Contracts\\Queue\\ShouldQueue;',
        },
        {
          file: 'app/Jobs/GenerateTenantReport.php',
          find: 'class GenerateTenantReport implements ShouldQueue, ShouldBeUnique',
          replace: 'class GenerateTenantReport implements ShouldQueue',
        },
        {
          file: 'app/Jobs/GenerateTenantReport.php',
          find: "    public function uniqueId(): string\n    {\n        return $this->report.'-report';\n    }\n\n    public function uniqueFor(): int\n    {\n        return 3600;\n    }\n\n",
          replace: '',
        },
      ],
    },
  ],

  'lock-leak-on-exception': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Jobs/SyncSupplierInventory.php',
        find: [
          '        $response = Http::timeout(60)->get(\'https://supplier.example/api/stock\');',
          '        $response->throw();',
          '',
          '        $items = $response->json(\'items\', []);',
          '',
          '        foreach ($items as $item) {',
          '            StockItem::updateOrCreate(',
          "                ['sku' => $item['sku']],",
          "                ['quantity' => (int) $item['quantity']],",
          '            );',
          '        }',
          '',
          '        InventorySync::create([\'item_count\' => count($items)]);',
          '',
          '        $lock->release();',
        ].join('\n'),
        replace: [
          '        try {',
          "            $response = Http::timeout(60)->get('https://supplier.example/api/stock');",
          '            $response->throw();',
          '',
          "            $items = $response->json('items', []);",
          '',
          '            foreach ($items as $item) {',
          '                StockItem::updateOrCreate(',
          "                    ['sku' => $item['sku']],",
          "                    ['quantity' => (int) $item['quantity']],",
          '                );',
          '            }',
          '',
          "            InventorySync::create(['item_count' => count($items)]);",
          '        } finally {',
          '            $lock->release();',
          '        }',
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-swallow-exception.patch',
      edits: [{
        file: 'app/Jobs/SyncSupplierInventory.php',
        find: [
          '        $response = Http::timeout(60)->get(\'https://supplier.example/api/stock\');',
          '        $response->throw();',
          '',
          '        $items = $response->json(\'items\', []);',
          '',
          '        foreach ($items as $item) {',
          '            StockItem::updateOrCreate(',
          "                ['sku' => $item['sku']],",
          "                ['quantity' => (int) $item['quantity']],",
          '            );',
          '        }',
          '',
          '        InventorySync::create([\'item_count\' => count($items)]);',
          '',
          '        $lock->release();',
        ].join('\n'),
        replace: [
          '        try {',
          "            $response = Http::timeout(60)->get('https://supplier.example/api/stock');",
          '            $response->throw();',
          '',
          "            $items = $response->json('items', []);",
          '',
          '            foreach ($items as $item) {',
          '                StockItem::updateOrCreate(',
          "                    ['sku' => $item['sku']],",
          "                    ['quantity' => (int) $item['quantity']],",
          '                );',
          '            }',
          '',
          "            InventorySync::create(['item_count' => count($items)]);",
          '        } catch (\\Throwable $e) {',
          '            report($e);',
          '            $lock->release();',
          '',
          '            return;',
          '        }',
          '',
          '        $lock->release();',
        ].join('\n'),
      }],
    },
  ],

  'lazy-loading-in-accessor': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/PostController.php',
        find: '        $posts = Post::query()\n            ->latest()',
        replace: '        $posts = Post::query()\n            ->with(\'author\')\n            ->latest()',
      }],
    },
    {
      name: 'distractor-load-in-accessor.patch',
      edits: [{
        file: 'app/Models/Post.php',
        find: '        return $this->author->name;',
        replace: '        return $this->load(\'author\')->author->name;',
      }],
    },
    {
      name: 'distractor-disable-strictness.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: '    public function boot(): void\n    {\n        //\n    }',
        replace: '    public function boot(): void\n    {\n        \\Illuminate\\Database\\Eloquent\\Model::preventLazyLoading(false);\n    }',
      }],
    },
  ],

  'morph-map-hardcoded-type': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/AdminCommentController.php',
        find: "            ->where('commentable_type', self::MODEL_TYPES[$model] ?? null)",
        replace: "            ->where('commentable_type', isset(self::MODEL_TYPES[$model]) ? app(self::MODEL_TYPES[$model])->getMorphClass() : null)",
      }],
    },
    {
      name: 'distractor-remove-morph-map.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: '    public function boot(): void\n    {\n        Relation::enforceMorphMap([\n            \'order\' => Order::class,\n            \'listing\' => Listing::class,\n        ]);\n    }',
        replace: '    public function boot(): void\n    {\n        //\n    }',
      }],
    },
  ],

  'idor-nested-resource': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/CustomerInvoiceController.php',
        find: '    public function show(Customer $customer, Invoice $invoice)\n    {\n        return response()->json([',
        replace: [
          '    public function show(Customer $customer, Invoice $invoice)',
          '    {',
          '        abort_unless(auth()->user()?->customer_id === $invoice->customer_id, 403);',
          '        abort_unless($invoice->customer_id === $customer->id, 404);',
          '',
          '        return response()->json([',
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-authorize-customer.patch',
      edits: [{
        file: 'app/Http/Controllers/CustomerInvoiceController.php',
        find: '    public function show(Customer $customer, Invoice $invoice)\n    {\n        return response()->json([',
        replace: '    public function show(Customer $customer, Invoice $invoice)\n    {\n        $this->authorize(\'view\', $customer);\n\n        return response()->json([',
      }],
      newFiles: [{
        rel: 'app/Policies/CustomerPolicy.php',
        content: [
          '<?php',
          '',
          'namespace App\\Policies;',
          '',
          'use App\\Models\\Customer;',
          'use App\\Models\\User;',
          '',
          'class CustomerPolicy',
          '{',
          '    public function view(User $user, Customer $customer): bool',
          '    {',
          '        return true;',
          '    }',
          '}',
        ].join('\n') + '\n',
      }],
    },
  ],

  'rate-limiter-missing-key': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: '            return Limit::perMinute(3);',
        replace: '            return Limit::perMinute(3)->by($request->ip());',
      }],
    },
    {
      name: 'distractor-key-by-forwarded-header.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: '            return Limit::perMinute(3);',
        replace: "            return Limit::perMinute(3)->by($request->header('X-Forwarded-For', $request->ip()));",
      }],
    },
  ],

  'runtime-env-after-cache': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Services/BillingGateway.php',
        find: '        $this->apiKey = env(\'PAYRAIL_API_KEY\', \'sandbox-key\');',
        replace: [
          "        $this->apiKey = (string) config('services.billing.key', '');",
          '',
          "        if ($this->apiKey === '') {",
          "            throw new \\RuntimeException('Billing gateway is not configured: missing services.billing.key.');",
          '        }',
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-env-first-fallback.patch',
      edits: [{
        file: 'app/Services/BillingGateway.php',
        find: "        $this->apiKey = env('PAYRAIL_API_KEY', 'sandbox-key');",
        replace: "        $this->apiKey = env('PAYRAIL_API_KEY') ?? (string) config('services.billing.key', '');",
      }],
      newFiles: [{
        rel: 'config/services.php',
        content: [
          '<?php',
          '',
          'return [',
          '',
          '    \'billing\' => [',
          "        'key' => env('PAYRAIL_API_KEY', 'sandbox-key'),",
          '    ],',
          '',
          '];',
        ].join('\n') + '\n',
      }],
    },
    {
      name: 'distractor-superglobal-read.patch',
      edits: [{
        file: 'app/Services/BillingGateway.php',
        find: "        $this->apiKey = env('PAYRAIL_API_KEY', 'sandbox-key');",
        replace: "        $this->apiKey = $_ENV['PAYRAIL_API_KEY'] ?? 'sandbox-key';",
      }],
    },
  ],

  'singleton-request-state': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: '        $this->app->singleton(TenantContext::class, function () {',
        replace: '        $this->app->bind(TenantContext::class, function () {',
      }],
    },
    {
      name: 'distractor-injected-request.patch',
      edits: [{
        file: 'app/Providers/AppServiceProvider.php',
        find: [
          '        $this->app->singleton(TenantContext::class, function () {',
          "            $slug = (string) request()->header('X-Tenant', '');",
        ].join('\n'),
        replace: [
          '        $this->app->singleton(TenantContext::class, function ($app) {',
          "            $slug = (string) $app['request']->header('X-Tenant', '');",
        ].join('\n'),
      }],
    },
  ],

  'soft-delete-unique-email': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Requests/CreateContactRequest.php',
        find: "            'email' => ['required', 'email', Rule::unique('contacts', 'email')],",
        replace: "            'email' => ['required', 'email', Rule::unique('contacts', 'email')->whereNull('deleted_at')],",
      }],
      newFiles: [{
        rel: 'database/migrations/2024_10_02_000000_scope_unique_email_to_active_contacts.php',
        content: [
          '<?php',
          '',
          'use Illuminate\\Database\\Migrations\\Migration;',
          'use Illuminate\\Database\\Schema\\Blueprint;',
          'use Illuminate\\Support\\Facades\\Schema;',
          '',
          'return new class extends Migration',
          '{',
          '    /**',
          '     * Run the migrations.',
          '     */',
          '    public function up(): void',
          '    {',
          '        Schema::table(\'contacts\', function (Blueprint $table) {',
          "            $table->dropUnique(['email']);",
          "            $table->unique(['email', 'deleted_at']);",
          '        });',
          '    }',
          '',
          '    /**',
          '     * Reverse the migrations.',
          '     */',
          '    public function down(): void',
          '    {',
          '        Schema::table(\'contacts\', function (Blueprint $table) {',
          "            $table->dropUnique(['email', 'deleted_at']);",
          '            $table->unique(\'email\');',
          '        });',
          '    }',
          '};',
        ].join('\n') + '\n',
      }],
    },
    {
      name: 'distractor-rule-only.patch',
      edits: [{
        file: 'app/Http/Requests/CreateContactRequest.php',
        find: "            'email' => ['required', 'email', Rule::unique('contacts', 'email')],",
        replace: "            'email' => ['required', 'email', Rule::unique('contacts', 'email')->whereNull('deleted_at')],",
      }],
    },
    {
      name: 'distractor-index-without-drop.patch',
      newFiles: [{
        rel: 'database/migrations/2024_10_02_000000_add_composite_unique_index.php',
        content: [
          '<?php',
          '',
          'use Illuminate\\Database\\Migrations\\Migration;',
          'use Illuminate\\Database\\Schema\\Blueprint;',
          'use Illuminate\\Support\\Facades\\Schema;',
          '',
          'return new class extends Migration',
          '{',
          '    /**',
          '     * Run the migrations.',
          '     */',
          '    public function up(): void',
          '    {',
          '        Schema::table(\'contacts\', function (Blueprint $table) {',
          "            $table->unique(['email', 'deleted_at']);",
          '        });',
          '    }',
          '',
          '    /**',
          '     * Reverse the migrations.',
          '     */',
          '    public function down(): void',
          '    {',
          '        Schema::table(\'contacts\', function (Blueprint $table) {',
          "            $table->dropUnique(['email', 'deleted_at']);",
          '        });',
          '    }',
          '};',
        ].join('\n') + '\n',
      }],
    },
  ],

  'provider-error-as-success': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/CheckoutController.php',
        find: '        $reference = $response->json(\'reference\');',
        replace: [
          '        $response->throw();',
          '',
          "        $reference = (string) $response->json('reference');",
          '',
          "        if ($reference === '') {",
          "            abort(502, 'provider did not return a payment reference');",
          '        }',
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-throw-but-mark-paid.patch',
      edits: [{
        file: 'app/Http/Controllers/CheckoutController.php',
        find: '        $reference = $response->json(\'reference\');',
        replace: [
          '        try {',
          '            $response->throw();',
          '        } catch (\\Throwable $e) {',
          '            report($e);',
          '        }',
          '',
          "        $reference = (string) $response->json('reference');",
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-status-only-check.patch',
      edits: [{
        file: 'app/Http/Controllers/CheckoutController.php',
        find: '        $reference = $response->json(\'reference\');',
        replace: [
          '        if (! $response->successful()) {',
          "            abort(502, 'provider error');",
          '        }',
          '',
          "        $reference = (string) $response->json('reference');",
        ].join('\n'),
      }],
    },
  ],

  'mass-assignment-silent-drop': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Models/User.php',
        find: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n    ];",
        replace: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n        'timezone',\n        'marketing_opt_in',\n    ];",
      }],
    },
    {
      name: 'distractor-partial-fillable.patch',
      edits: [{
        file: 'app/Models/User.php',
        find: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n    ];",
        replace: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n        'timezone',\n    ];",
      }],
    },
    {
      name: 'distractor-update-all.patch',
      edits: [
        {
          file: 'app/Http/Controllers/ProfileController.php',
          find: '        $user->update($request->validated());',
          replace: '        $user->update($request->all());',
        },
        {
          file: 'app/Models/User.php',
          find: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n    ];",
          replace: "    protected $fillable = [\n        'name',\n        'email',\n        'password',\n        'timezone',\n        'marketing_opt_in',\n        'is_admin',\n    ];",
        },
      ],
    },
  ],

  'signed-url-toggle-replay': [
    {
      name: 'solution.patch',
      edits: [{
        file: 'app/Http/Controllers/UnsubscribeController.php',
        find: [
          '        $user->update([',
          '            \'marketing_opt_in\' => ! $user->marketing_opt_in,',
          '        ]);',
          '',
          '        Mail::to($user->email)->send(new UnsubscribeConfirmed);',
        ].join('\n'),
        replace: [
          '        $user->update([',
          "            'marketing_opt_in' => false,",
          '        ]);',
          '',
          "        if ($user->wasChanged('marketing_opt_in')) {",
          '            Mail::to($user->email)->send(new UnsubscribeConfirmed);',
          '        }',
        ].join('\n'),
      }],
    },
    {
      name: 'distractor-set-but-spam-mail.patch',
      edits: [{
        file: 'app/Http/Controllers/UnsubscribeController.php',
        find: "            'marketing_opt_in' => ! $user->marketing_opt_in,",
        replace: "            'marketing_opt_in' => false,",
      }],
    },
  ],
};

const cfg = loadConfig();
const scenarios = discoverScenarios(cfgPath(cfg, 'scenarios'));
let generated = 0;
const failures = [];

for (const scenario of scenarios) {
  const defs = PATCHES[scenario.id];
  if (!defs) {
    console.error(`NOTE: no patch definitions for "${scenario.id}" (skipped)`);
    continue;
  }

  for (const def of defs) {
    const ws = path.join(cfg._root, cfg.paths.var, 'scratch', `genpatch-${scenario.id}`);
    materializeWorkspace({
      baseDir: path.join(cfg._root, cfg.paths.base),
      scenarioDir: scenario.dir,
      cell: cfg.matrix[0],
      outDir: ws,
      withHidden: false,
      vendorDir: null,
    });

    for (const edit of def.edits ?? []) {
      const abs = path.join(ws, edit.file);
      if (!existsSync(abs)) {
        failures.push(`${scenario.id}/${def.name}: missing file ${edit.file}`);
        continue;
      }
      const content = readFileSync(abs, 'utf8');
      const occurrences = content.split(edit.find).length - 1;
      if (occurrences !== 1) {
        failures.push(`${scenario.id}/${def.name}: find-string in ${edit.file} occurs ${occurrences} times (expected 1)`);
        continue;
      }
      writeFileSync(abs, content.replace(edit.find, edit.replace));
    }

    for (const nf of def.newFiles ?? []) {
      const dest = path.join(ws, nf.rel);
      mkdirSync(path.dirname(dest), { recursive: true });
      writeFileSync(dest, nf.content + (nf.content.endsWith('\n') ? '' : '\n'));
    }

    spawnSync('git', ['add', '-N', '.'], { cwd: ws });

    const diff = spawnSync('git', ['diff', '--no-color', '--binary'], { cwd: ws, encoding: 'utf8' });
    if (diff.status !== 0) {
      failures.push(`${scenario.id}/${def.name}: git diff failed: ${diff.stderr}`);
      rmSync(ws, { recursive: true, force: true });
      continue;
    }
    if (!diff.stdout.trim()) {
      failures.push(`${scenario.id}/${def.name}: empty diff`);
      rmSync(ws, { recursive: true, force: true });
      continue;
    }

    const dest = path.join(scenario.dir, 'patches', def.name);
    mkdirSync(path.dirname(dest), { recursive: true });
    writeFileSync(dest, diff.stdout);
    generated++;
    rmSync(ws, { recursive: true, force: true });
  }
}

console.error(`generated ${generated} patches`);
if (failures.length) {
  console.error('FAILURES:');
  for (const f of failures) console.error(` - ${f}`);
  process.exit(1);
}
