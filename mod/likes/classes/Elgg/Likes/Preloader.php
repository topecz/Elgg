<?php

namespace Elgg\Likes;

class Preloader {

	/**
	 * @var DataService
	 */
	protected $data;

	public function __construct(DataService $data) {
		$this->data = $data;
	}

	/**
	 * @param \ElggRiverItem[]|\ElggEntity[] $items
	 * @return void
	 */
	public function preloadForList(array $items) {
		$guids = $this->getGuidsToPreload($items);
		if (count($guids) < 2) {
			return;
		}

		$this->preloadCurrentUserLikes($guids);

		$guids_remaining = $this->preloadCountsFromHook($this->getEntities($guids));
		if ($guids_remaining) {
			$this->preloadCountsFromQuery($guids_remaining);
		}
	}

	/**
	 * @param int[] $guids
	 */
	protected function preloadCountsFromQuery(array $guids) {
		$count_rows = elgg_get_annotations([
			'annotation_names' => 'likes',
			'guids' => $guids,
			'selects' => ['e.guid', 'COUNT(*) AS cnt'],
			'group_by' => 'e.guid',
			'limit' => false,
			'callback' => false,
		]);
		foreach ($guids as $guid) {
			$this->data->setNumLikes($guid, 0);
		}
		foreach ($count_rows as $row) {
			$this->data->setNumLikes($row->guid, $row->cnt);
		}
	}

	/**
	 * @param \ElggEntity[] $entities
	 * @return int[]
	 */
	protected function preloadCountsFromHook(array $entities) {
		$guids_not_loaded = [];

		foreach ($entities as $entity) {
			// BC with likes_count(). If this hook is used this preloader may not be of much help.
			$type = $entity->getType();
			$params = ['entity' => $entity];

			$num_likes = elgg_trigger_plugin_hook('likes:count', $type, $params, false);
			if ($num_likes) {
				$this->data->setNumLikes($entity->guid, $num_likes);
			} else {
				$guids_not_loaded[] = $entity->guid;
			}
		}

		return $guids_not_loaded;
	}

	/**
	 * @param int[] $guids
	 */
	protected function preloadCurrentUserLikes(array $guids) {
		$owner_guid = elgg_get_logged_in_user_guid();
		if (!$owner_guid) {
			return;
		}

		$annotation_rows = elgg_get_annotations([
			'annotation_names' => 'likes',
			'annotation_owner_guids' => $owner_guid,
			'guids' => $guids,
			'limit' => false,
			'callback' => false,
		]);

		foreach ($guids as $guid) {
			$this->data->setLikedByCurrentUser($guid, false);
		}
		foreach ($annotation_rows as $row) {
			$this->data->setLikedByCurrentUser($row->entity_guid, true);
		}
	}

	/**
	 * @param \ElggRiverItem[]|\ElggEntity[] $items
	 * @return int[]
	 */
	protected function getGuidsToPreload(array $items) {
		$guids = [];

		foreach ($items as $item) {
			// TODO remove duplication of @link likes_river_menu_setup()

			if ($item instanceof \ElggRiverItem) {
				// only like group creation #3958
				if ($item->type == "group" && $item->view != "river/group/create") {
					continue;
				}

				$type = $item->type;
				$subtype = $item->subtype;
				$likable = (bool) elgg_trigger_plugin_hook('likes:is_likable', "$type:$subtype", [], false);
				if (!$likable) {
					continue;
				}

				if ($item->annotation_id != 0) {
					continue;
				}

				if ($item->object_guid) {
					$guids[$item->object_guid] = true;
				}
			} elseif ($item instanceof \ElggEntity) {
				$type = $item->type;
				$subtype = $item->getSubtype();
				$likable = (bool) elgg_trigger_plugin_hook('likes:is_likable', "$type:$subtype", [], false);
				if ($likable) {
					$guids[$item->guid] = true;
				}
			}
		}
		return array_keys($guids);
	}

	/**
	 * Get entities in any order checking cache first
	 *
	 * @param int[] $guids
	 * @return \ElggEntity[]
	 */
	protected function getEntities(array $guids) {
		// most objects are already preloaded
		$entities = [];
		$fetch_guids = [];

		foreach ($guids as $guid) {
			$entity = _elgg_services()->entityCache->get($guid);
			if ($entity) {
				$entities[] = $entity;
			} else {
				$fetch_guids[] = $guid;
			}
		}
		if ($fetch_guids) {
			$fetched = elgg_get_entities([
				'guids' => $fetch_guids,
				'limit' => false,
			]);
			array_splice($entities, count($entities), 0, $fetched);
		}
		return $entities;
	}
}
