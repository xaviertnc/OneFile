<?php namespace OneFile;

use Exception;

/**
 * Helper to make generating HTML for grouped lists easier
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 01 May 2017
 *
 * Licensed under the MIT license. Please see LICENSE for more information.
 *
 *
 * EXAMPLE:
 * ========
 *
 * $listManager = new ListUiManager($model->listItems, ['groupByPropName1', 'groupByPropName2']);
 *
 * $html = '';
 *
 * foreach (listManager->uiSegments as $uiSegment))
 * {
 *     switch ($uiSegment->type)
 *     {
 * 	       case 'headerSegment' : $html .= Ui:renderGroupHeaderHtml($uiSegment);
 * 	       case 'openSegment'   : $html .= Ui:renderGroupOpenHtml($uiSegment);
 * 	       case 'closeSegment'  : $html .= Ui:renderGroupCloseHtml($uiSegment);
 * 	       case 'itemSegment'   : $html .= Ui:renderItemHtml($uiSegment);
 *     }
 * }
 *
 * echo $html;
 *
 */

class ListUiGroup
{
	public $id;
	public $level;
	public $idParts;
	public $groupByProp;
	public $parentGroup;
	public $itemCount = 0;
	public $listIndex = 0;
	public $aggregates = [];

	public function __construct($idParts = [], $level = 0, $groupByProp = null, $parentGroup = null)
	{
		$this->level = $level;
		$this->idParts = is_string($idParts) ? [$idParts] : $idParts;
		$this->id = implode('~', $this->idParts);
		$this->groupByProp = $groupByProp;
		$this->parentGroup = $parentGroup;
	}
}


class ListUiSegment
{
	public $type;
	public $item;
	public $group;
	public $itemGroupIndex;

	public function __construct($type, $item, $group, $itemGroupIndex = null)
	{
		$this->type = $type;
		$this->item = $item;
		$this->group = $group;
		$this->itemGroupIndex = $itemGroupIndex;
	}

	public function getItemListNumber()
	{
		return $this->group->listIndex . ($this->itemGroupIndex ? '.' . $this->itemGroupIndex : '');
	}
}


class ListGroupsUi
{
	public $uiSegments = [];

	protected $rootGroup;
	protected $topLevelListIndex = 1;

	public function __construct($listItems, $groupByProps, $aggregates = [])
	{
		$this->rootGroup = new ListUiGroup('root');
		$this->generateUiSegments($listItems, $groupByProps, $aggregates);
	}

	public function getListIndex($listGroup)
	{
		if ( ! $listGroup->level) { return; }
		if ($listGroup->level === 1) { return $this->topLevelListIndex++; }
		return $listGroup->parentGroup->listIndex . '.' . ($listGroup->parentGroup->itemCount + 1);
	}

	public function getItemGroup($item, array $groupByProps)
	{
		if (empty($groupByProps)) { throw new Exception('ListGoupsUi::getItemGroup(), $groupByProps EMPTY!'); }
		$newGroupLevel = 0;
		$newGroupIdParts = ['root'];
		foreach ($groupByProps as $groupByProp)
		{
			$itemPropValue = $item->{$groupByProp};
			if ($itemPropValue)
			{
				$newGroupIdParts[] = $itemPropValue;
				$newGroupLevel++;
			}
		}
		$newGroupParent = ($newGroupLevel === 1) ? $this->rootGroup : null;
		return new ListUiGroup($newGroupIdParts, $newGroupLevel, $groupByProps[$newGroupLevel-1], $newGroupParent);
	}

	public function onSameBranch($group1, $group2)
	{
		return (!$group1->level or !$group2->level or $group1->idParts[1] === $group2->idParts[1]);
	}

	public function updateGroupAggregates($currentGroup, $item, $aggregateTypes, $aggregates)
	{
		foreach ($aggregateTypes as $aggregateType)
		{
			switch ($aggregateType)
			{
				case 'sum':
					$val = $item->{$aggregates['sum']};
					if (isset($currentGroup->aggregates['sum'])) { $currentGroup->aggregates['sum'] += $val; } else { $currentGroup->aggregates['sum'] = $val; }
					break;

				case 'count':
					if (isset($currentGroup->aggregates['count'])) { $currentGroup->aggregates['count'] += 1; } else { $currentGroup->aggregates['count'] = 1; }
			}
		}
	}

	public function closeAllLevelsFromTo($currentGroup, $targetGroup, $item)
	{
		if ($currentGroup->level < $targetGroup->level) { return; }
		$this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentGroup);
		$currentParentGroup = $currentGroup->parentGroup;
		while ($currentParentGroup->level and $currentParentGroup->level >= $targetGroup->level)
		{
			$this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentParentGroup);
			$currentParentGroup = $currentParentGroup->parentGroup;
		}
	}

	public function openNewLevelsFromTo($currentGroup, $targetGroup, $item, $groupByProps)
	{
		$dLevel = ($targetGroup->level - $currentGroup->level);
		if ($dLevel > 0)
		{
			$fillerLevels = $dLevel - 1;
			$newGroupParent = $currentGroup;
			$currentLevel = $currentGroup->level;
			for ($i = 1; $i <= $fillerLevels; $i++)
			{
				$newGroupLevel = $currentLevel + $i;
				$newGroupIdParts = $newGroupParent->idParts;
				$newGroupIdParts[] = $targetGroup->idParts[$newGroupLevel];
				$newGroup = new ListUiGroup($newGroupIdParts, $newGroupLevel, $groupByProps[$newGroupLevel-1], $newGroupParent);
				$newGroup->parentGroup->itemCount++;
				$newGroup->listIndex = $this->getListIndex($newGroup);
				$this->uiSegments[] = new ListUiSegment('headerSegment', $item, $newGroup, $newGroup->itemCount);
				$this->uiSegments[] = new ListUiSegment('openSegment', $item, $newGroup, $newGroup->itemCount);
				$newGroupParent = $newGroup;
			}
			$targetGroup->parentGroup = $newGroupParent;
		}
	}

	public function generateUiSegments($listItems, $groupByProps, $aggregates)
	{
		$currentGroup = $this->rootGroup;
		$aggregateTypes = array_keys($aggregates);
		foreach ($listItems as $item)
		{
			// Get ITEM GROUP ID + LEVEL.
			// Note: GROUP PARENT + LIST-INDEX is only set for LEVEL1 groups!
			$targetGroup = $this->getItemGroup($item, $groupByProps);

			// If TARGET and CURRENT groups are the same, DO NOTHING!
			// Just skip the next block and add the ITEM to the current group.
			if ($targetGroup->id !== $currentGroup->id)
			{
				// Ok, TARGET !== CURRENT
				// We need to close the gap between CURRENT and TARGET
				// or collapse the CURRENT BRANCH and create a new BRANCH to TARGET.
				if ($this->onSameBranch($targetGroup, $currentGroup))
				{
					// YES, TARGET is on the CURRENT BRANCH
					$currentGroupIdLength = strlen($currentGroup->id);
					$targetGroupIdLength = strlen($targetGroup->id);
					if ($targetGroupIdLength < $currentGroupIdLength)
					{
						// CURRENT is deeper than TARGET - Close all the open groups between current and target.
						$this->closeAllLevelsFromTo($currentGroup, $targetGroup, $item);
					}
					elseif ($targetGroupIdLength > $currentGroupIdLength)
					{
						// TARGET is deeper than CURRENT - Use CURRENT as PARENT or fill level gap with empty level groups.
						if ($targetGroup->level - $currentGroup->level === 1) { $targetGroup->parentGroup = $currentGroup; }
					}
					elseif ($currentGroup->level)
					{
						// Get FALLBACK LEVEL & GROUP
						// Close levels above fallback level and set CURRENT group to FALLBACK group
						$fallBackGroup = $currentGroup->parentGroup; $i = $fallBackGroup->level;
						$this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentGroup);
						while ($i and $fallBackGroup->idParts[$i] !== $targetGroup->idParts[$i])
						{
							$this->uiSegments[] = new ListUiSegment('closeSegment', $item, $fallBackGroup);
							$fallBackGroup = $fallBackGroup->parentGroup;
							$i--;
						}
						$currentGroup = $fallBackGroup;
					}
				}
				else
				{
					// WE are NOT on the CURRENT BRANCH!
					// Close all the open groups between current and root. (i.e. On this branch)
					$this->closeAllLevelsFromTo($currentGroup, $this->rootGroup, $item);
					// Set CURRENT group === ROOT group!
					$currentGroup = $this->rootGroup;
				}

				// Add filler groups from CURRENT to TARGET and update TARGET PARENT if required
				$this->openNewLevelsFromTo($currentGroup, $targetGroup, $item, $groupByProps);

				// With the gap closed, TARGET group can now be set as CURRENT
				$currentGroup = $targetGroup;

				// Update new CURRENT group listIndex if required
				if ( ! $currentGroup->listIndex and $currentGroup->level) { $currentGroup->listIndex = $this->getListIndex($currentGroup); }

				// Headers count as items on the parent group!
				// So, inrease the parent-item-count to make ListSegmentUi::getItemListNumber() work correctly
				$currentGroup->parentGroup->itemCount++;
				$this->uiSegments[] = new ListUiSegment('headerSegment', $item, $currentGroup, $currentGroup->itemCount);
				$this->uiSegments[] = new ListUiSegment('openSegment', $item, $currentGroup, $currentGroup->itemCount);
			}

			// Add item segment
			$currentGroup->itemCount++;
			$this->uiSegments[] = new ListUiSegment('itemSegment', $item, $currentGroup, $currentGroup->itemCount);
			$this->updateGroupAggregates($currentGroup, $item, $aggregateTypes, $aggregates);
		}

		// Close out the structure
		$this->closeAllLevelsFromTo($currentGroup, $this->rootGroup, $item);
	}
}
