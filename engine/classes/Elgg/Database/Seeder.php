<?php

namespace Elgg\Database;

use Elgg\Database\Seeds\Seed;
use Elgg\PluginHooksService;

/**
 * Seeder class
 *
 * Populates the database with rows for testing
 *
 * @access private
 */
class Seeder {

	/**
	 * @var PluginHooksService
	 */
	protected $hooks;

	/**
	 * Seeder constructor.
	 *
	 * @param PluginHooksService $hooks Hooks registration service
	 */
	public function __construct(PluginHooksService $hooks) {
		$this->hooks = $hooks;
	}

	/**
	 * Load seed scripts
	 *
	 * @return void
	 */
	public function seed($limit = null) {
		if (!$limit) {
			$limit = max(elgg_get_config('default_limit'), 20);
		}

		$ia = elgg_set_ignore_access(true);

		$ha = access_get_show_hidden_status();
		access_show_hidden_entities(true);

		$seeds = $this->hooks->trigger('seeds', 'database', null, []);

		foreach ($seeds as $seed) {
			if (!class_exists($seed)) {
				elgg_log("Seeding class $seed not found", 'ERROR');
				continue;
			}
			if (!is_subclass_of($seed, Seed::class)) {
				elgg_log("Seeding class $seed does not extend " . Seed::class, 'ERROR');
				continue;
			}
			$seeder = new $seed($limit);
			elgg_log('Starting seeding with ' . get_class($seeder));
			$seeder->seed();
			elgg_log('Finished seeding with ' . get_class($seeder));
		}

		access_show_hidden_entities($ha);
		elgg_set_ignore_access($ia);
	}

	/**
	 * Remove all seeded entities
	 * @return void
	 */
	public function unseed() {

		$ia = elgg_set_ignore_access(true);

		$ha = access_get_show_hidden_status();
		access_show_hidden_entities(true);

		$seeds = $this->hooks->trigger('seeds', 'database', null, []);

		foreach ($seeds as $seed) {
			if (!class_exists($seed)) {
				elgg_log("Seeding class $seed not found", 'ERROR');
				continue;
			}
			if (!is_subclass_of($seed, Seed::class)) {
				elgg_log("Seeding class $seed does not extend " . Seed::class, 'ERROR');
				continue;
			}
			$seeder = new $seed();
			elgg_log('Starting unseeding with ' . get_class($seeder));
			$seeder->unseed();
			elgg_log('Finished unseeding with ' . get_class($seeder));
		}

		access_show_hidden_entities($ha);
		elgg_set_ignore_access($ia);
	}
}