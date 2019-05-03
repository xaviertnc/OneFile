<?php namespace OneFile;

use Exception;

/**
 * Helper to make generating HTML for grouped lists easier
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 01 May 2017
 *
 * Updated: C. Moller - 03 May 2019
 *   - Fixed fatal errors in group-id calculation logic.
 *       count(group->idParts) vs. strlen(group->id)
 *   - Improve grouping and listIndex logic to accommodate more edge cases!
 *   - Allow unique items (WITHOUT A GROUP) in ROOT group.
 *   - Created documentation and tests.
 *   - Improved code.
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
 *         case 'headerSegment' : $html .= Ui:renderGroupHeaderHtml($uiSegment);
 *         case 'openSegment'   : $html .= Ui:renderGroupOpenHtml($uiSegment);
 *         case 'closeSegment'  : $html .= Ui:renderGroupCloseHtml($uiSegment);
 *         case 'itemSegment'   : $html .= Ui:renderItemHtml($uiSegment);
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

  /**
   * We need to know if a grouping property is SET!
   * Values like: NULL and '' cannot be used as group ID parts
   * because they are invalid or empty!
   * A "0" string value however, might be a valid property value.
   * $propZeroAllowed indicates wheter we want to treat "0"
   * as an ALLOWED property value or not!
   * @var Boolean
   */
  protected $propZeroAllowed;

  /**
   * Determines where we start the numbering of TOP LEVEL ITEMS
   * @var integer
   */
  protected $topLevelListIndex = 1;

  public function __construct($listItems, $groupByProps, $aggregates = [], $propZeroAllowed = false)
  {
    $this->rootGroup = new ListUiGroup('root');
    $this->generateUiSegments($listItems, $groupByProps, $aggregates);
    $this->propZeroAllowed = $propZeroAllowed;
  }

  private function array_get($array, $key, $default = null)
  {
    return isset($array[$key]) ? $array[$key] : $default;
  }

  /**
   * Creates a new ListUiGroup instance for the given list item.
   * The level and ID of the group is determined by the number and combination
   * of valid grouping property values found on the item!
   *
   * @param  stdClass $item         The data object representing the list item
   * @param  array  $groupByProps   A list of item object properties to group by.
   * @return ListUiGroup
   */
  public function getItemGroup($item, array $groupByProps)
  {
    if (empty($groupByProps))
    {
      throw new Exception('ListGroupsUi::getItemGroup(), $groupByProps EMPTY!');
    }
    $newGroupLevel = 0;
    $newGroupGroupByProp = '';
    $newGroupIdParts = ['root'];
    foreach ($groupByProps as $groupByProp)
    {
      if (property_exists($item, $groupByProp))
      {
        $itemProperty = $item->{$groupByProp};
        if ($itemProperty or ($this->propZeroAllowed and $itemProperty == 0))
        {
          $newGroupIdParts[] = $itemProperty;
          $newGroupGroupByProp = $groupByProp; // We want the LAST prop name added!
          $newGroupLevel++;
        }
      }
    }
    if ( ! $newGroupLevel) { return; } // No group for THIS ITEM!
    $newGroupParent = ($newGroupLevel === 1) ? $this->rootGroup : null;
    return new ListUiGroup(
      $newGroupIdParts,
      $newGroupLevel,
      $newGroupGroupByProp,
      $newGroupParent
    );
  }

  public function getGroupListIndex($group)
  {
    if ($group->level and $group->level === 1)
    {
      return $this->topLevelListIndex++;
    }
    return empty($group->parentGroup) ? 0
     : $group->parentGroup->listIndex . '.' . $group->parentGroup->itemCount;
  }

  public function onSameBranch($group1, $group2)
  {
    return (
      $group1->level == 0 or
      $group2->level == 0 or
      $group1->idParts[1] === $group2->idParts[1] // NOTE: index == 1 is the part AFTER 'root'
    );
  }

  public function updateGroupAggregates($currentGroup, $item, $aggregateTypes, $aggregates)
  {
    foreach ($aggregateTypes as $aggregateType)
    {
      switch ($aggregateType)
      {
        case 'sum':
          $val = $item->{$aggregates['sum']};
          if (isset($currentGroup->aggregates['sum']))
          {
            $currentGroup->aggregates['sum'] += $val;
          }
          else
          {
            $currentGroup->aggregates['sum'] = $val;
          }
          break;

        case 'count':
          if (isset($currentGroup->aggregates['count']))
          {
            $currentGroup->aggregates['count'] += 1;
          }
          else
          {
            $currentGroup->aggregates['count'] = 1;
          }
      }
    }
  }

  public function closeAllLevelsFromTo($currentGroup, $targetGroup, $item = null)
  {
    if ($currentGroup->level == 0 or $currentGroup->level < $targetGroup->level) { return; }
    $this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentGroup);
    $currentParentGroup = $currentGroup->parentGroup;
    $level = $currentParentGroup->level;
    while ($currentParentGroup and $level and
      $currentParentGroup->idParts[$level] != $this->array_get($targetGroup->idParts, $level))
    {
      $this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentParentGroup);
      $currentParentGroup = $currentParentGroup->parentGroup;
      $level = $currentParentGroup->level;
    }
  }

  // This function is ALL about adding OPEN SEGMENTS and SETTING TARGET-GROUP's PARENT!
  public function openNewLevelsFromTo($currentGroup, $targetGroup, $item, $groupByProps)
  {
    $dLevel = ($targetGroup->level - $currentGroup->level);
    if ($dLevel < 0)
    {
      while ($currentGroup->level and $currentGroup->level > $targetGroup->level)
      {
        $currentGroup = $currentGroup->parentGroup;
      }
      $level = $currentGroup->level;
      while ($level > 0 and $currentGroup->idParts[$level] != $targetGroup->idParts[$level])
      {
        $currentGroup = $currentGroup->parentGroup;
        $level--;
      }
      $dLevel = ($targetGroup->level - $currentGroup->level);
    }
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
        $newGroup = new ListUiGroup(
          $newGroupIdParts,                 // Array       $idParts
          $newGroupLevel,                   // Integer     $level
          $groupByProps[$newGroupLevel-1],  // Array       $groupByProp
          $newGroupParent                   // ListUiGroup $groupParent
        );
        $newGroup->parentGroup->itemCount++;
        $newGroup->listIndex = $this->getGroupListIndex($newGroup);
        $this->uiSegments[] = new ListUiSegment('headerSegment', $item, $newGroup, $newGroup->itemCount);
        $this->uiSegments[] = new ListUiSegment('openSegment', $item, $newGroup, $newGroup->itemCount);
        $newGroupParent = $newGroup;
      }
      $targetGroup->parentGroup = $newGroupParent;
    }
    else
    {
      $targetGroup->parentGroup = $this->rootGroup;
    }
  }

  public function generateUiSegments($listItems, $groupByProps, $aggregates)
  {
    $currentGroup = $this->rootGroup;
    $aggregateTypes = array_keys($aggregates);
    $itemIndex = 1;
    foreach ($listItems as $item)
    {

      $item->_index = $itemIndex++;

      // Gets ITEM GROUP + GROUP ID + GROUP LEVEL.
      // NOTE: getItemGroup() Only sets the PARENT GROUP for LEVEL1 groups!
      // openNewLevelsFromTo() will set the parent group for groups NOT
      // directly under root. We first need to create "filler groups"
      // between ROOT and THIS GROUP to have a parent group to assign.
      // The TARGET GROUP is the group of THIS ITEM (e.g. Required group)
      // The CURRENT GROUP is the group of THE LAST ITEM(s) added.
      $targetGroup = $this->getItemGroup($item, $groupByProps, $itemIndex);

      // If TARGET and CURRENT groups are the same, DO NOTHING!
      // Just skip the next block and add THIS ITEM to the current group.
      if ($targetGroup and $targetGroup->id !== $currentGroup->id)
      {
        // Ok, TARGET !== CURRENT
        // We need to close the gap between CURRENT and TARGET
        // or collapse the CURRENT BRANCH and create a new BRANCH to TARGET.
        if ($this->onSameBranch($targetGroup, $currentGroup))
        {
          // YES, TARGET is on the CURRENT BRANCH
          $currentGroupIdLength = count($currentGroup->idParts);
          $targetGroupIdLength = count($targetGroup->idParts);

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
            // Determine the FALLBACK LEVEL & GROUP + Update the CURRENT GROUP...

            // The FALLBACK group is the FIRST PARENT GROUP shared by
            // BOTH the CURRENT and TARGET group!
            $fallBackGroup = $currentGroup->parentGroup; $i = $fallBackGroup->level;

            // Close the UI levels above the fallback level
            $this->uiSegments[] = new ListUiSegment('closeSegment', $item, $currentGroup);
            while ($i and $fallBackGroup->idParts[$i] !== $targetGroup->idParts[$i])
            {
              $this->uiSegments[] = new ListUiSegment('closeSegment', $item, $fallBackGroup);
              $fallBackGroup = $fallBackGroup->parentGroup;
              $i--;
            }

            // Change the CURRENT group to prompt openNewLevelsFromTo() to add filler
            // groups between the new CURRENT (i.e. fallback) group and the TARGET group,
            // if required!  See a few steps down...
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

        // Headers count as items on the parent group!
        // So, inrease the parent-item-count to make ListUiGroup::getGroupListIndex() and
        // ListUiSegment::getItemListNumber() work correctly
        $currentGroup->parentGroup->itemCount++;

        // Update new CURRENT group listIndex if required
        if ( ! $currentGroup->listIndex and $currentGroup->level) {
          $currentGroup->listIndex = $this->getGroupListIndex($currentGroup);
        }

        $this->uiSegments[] = new ListUiSegment('headerSegment', $item, $currentGroup, $currentGroup->itemCount);
        $this->uiSegments[] = new ListUiSegment('openSegment', $item, $currentGroup, $currentGroup->itemCount);
      }

      if ( ! $targetGroup) {
        // Closes all groups to add (no group) ROOT ITEM(s)!
        $this->closeAllLevelsFromTo($currentGroup, $this->rootGroup);
        $currentGroup = $this->rootGroup;
      }

      // Add item segment
      $currentGroup->itemCount++;
      $this->uiSegments[] = new ListUiSegment('itemSegment', $item, $currentGroup, $currentGroup->itemCount);
      $this->updateGroupAggregates($currentGroup, $item, $aggregateTypes, $aggregates);
    }

    // Close out the structure (If we're not already on ROOT LEVEL)
    $this->closeAllLevelsFromTo($currentGroup, $this->rootGroup);
  }
}
