ANATOMY OF A GROUPED LIST UI!
=============================

Example List:
-------------

  [
    { id:1 , type: 'flower', color: 'red'   , variant: 'daisy'  , description: 'F1' },
    { id:2 , type: 'flower', color: 'green' , variant: 'daisy'  , description: 'F2' },
    { id:3 , type: 'flower', color: 'green' , variant: 'daisy'  , description: 'F3' },
    { id:4 , type: 'flower', color: 'blue'  , variant: 'violet' , description: 'F4' },
    { id:5 , type: 'flower', color: 'blue'  , variant: 'violet' , description: 'F5' },
    { id:6 , type: 'dress' , color: 'black' , variant: 'evening', description: 'D1' },
    { id:7 , type: 'dress' , color: 'black' , variant: 'evening', description: 'D2' },
    { id:8 , type: 'dress' , color: 'gold'  , variant: 'evening', description: 'D3' },
    { id:9 , type: 'dress' , color: 'red'   , variant: 'day'    , description: 'D4' },
    { id:10, type: 'dress' , color: 'white' , variant: 'day'    , description: 'D5' },
    { id:11, type: 'car'   , color: 'white' , variant: 'work'   , description: 'C1' },
    { id:12, type: 'car'   , color: 'white' , variant: 'work'   , description: 'C2' },
    { id:13, type: 'car'   , color: 'red'   , variant: 'play'   , description: 'C3' },
    { id:14, type: 'car'   , color: 'silver', variant: 'work'   , description: 'C4' },
    { id:15, type: 'car'   , color: 'silver', variant: 'work'   , description: 'C5' }

    { id:16, type: 'car'   , color: ''      , variant: 'work'   , description: 'C6 '}
    { id:17, type: ''      , color: ''      , variant: 'work'   , description: 'A1' }
    { id:18, type: 'car'   , color: ''      , variant: ''       , description: 'C7 '}

    { id:19, type: ''      , color: ''      , variant: ''       , description: 'B1 '}
  ]



Example Implementation Code:
----------------------------

  ListUiManager::construct($listItems, $groupByProps, $aggregates = [])

  $listManager = new ListUiManager($model->listItems, ['groupByPropName1', 'groupByPropName2']);
  e.g. $listManager = new ListUiManager($model->listItems, ['type', 'color'], ['count' => 'id']);

  $html = '';

  foreach (listManager->uiSegments as $uiSegment))
  {
     switch ($uiSegment->type)
     {
         case 'headerSegment' : $html .= Ui:renderGroupHeaderHtml($uiSegment);
         case 'openSegment'   : $html .= Ui:renderGroupOpenHtml($uiSegment);
         case 'closeSegment'  : $html .= Ui:renderGroupCloseHtml($uiSegment);
         case 'itemSegment'   : $html .= Ui:renderItemHtml($uiSegment);
    }
  }



List Segment Type Examples:
---------------------------

  headerSegment:  <h1><i class="icon-plus"></i> { $item->type } - { $item->color }</h1>

  openSegment:    <ul id="{$group->id}" class="list-group">

  itemSegment:      <li id="{$item->id}" class="item">{ $item->description }</li>

  closeSegment:   </ul>



List Visual Layout:
-------------------

    ROOT HEAD SEGMENT: group = { level: 0, id: root, groupBy: none }
    ROOT OPEN SEGMENT: group = { level: 0, id: root, groupBy: none }

      HEAD SEGMENT: group = { level: 1, id: root~flower, groupBy: type }
      OPEN SEGMENT: group = { level: 1, id: root~flower, groupBy: type }

        HEAD SEGMENT: group = { level: 2, id: root~flower~red, groupBy: color }
        OPEN SEGMENT: group = { level: 2, id: root~flower~red, groupBy: color }

          HEAD SEGMENT: group = { level: 2, id: root~flower~red~daisy, groupBy: variant }
          OPEN SEGMENT: group = { level: 2, id: root~flower~red~daisy, groupBy: variant }

            ITEM SEGMENT: item = { id: 1, type: flower, color: red, variant: daisy, description: F1 }

          CLOSE SEGMENT: group = { id: root~flower~red~daisy }

        CLOSE SEGMENT: group = { id: root~flower~red }

        HEAD SEGMENT: group = { level: 2, id: root~flower~green, groupBy: color }
        OPEN SEGMENT: group = { level: 2, id: root~flower~green, groupBy: color }

          HEAD SEGMENT: group = { level: 2, id: root~flower~green~daisy, groupBy: variant }
          OPEN SEGMENT: group = { level: 2, id: root~flower~green~daisy, groupBy: variant }

            ITEM: { id: 2, type: flower, color: green, variant: daisy, description: F2 }
            ITEM: { id: 3, type: flower, color: green, variant: daisy, description: F3 }

          CLOSE SEGMENT: group = { id: root~flower~green~daisy }

        CLOSE SEGMENT: group = { id: root~flower~green }

        HEAD SEGMENT: group = { level: 2, id: root~flower~blue, groupBy: color }
        OPEN SEGMENT: group = { level: 2, id: root~flower~blue, groupBy: color }

          HEAD SEGMENT: group = { level: 2, id: root~flower~blue~violet, groupBy: variant }
          OPEN SEGMENT: group = { level: 2, id: root~flower~blue~violet, groupBy: variant }

            ITEM SEGMENT: { id: 4, type: flower, color: blue, variant: violet, description: F4 }
            ITEM SEGMENT: { id: 5, type: flower, color: blue, variant: violet, description: F5 }

          CLOSE SEGMENT: group = { id: root~flower~blue~violet }

        CLOSE SEGMENT: group = { id: root~flower~blue }

      CLOSE SEGMENT: group = { id: root~flower }

      ...

      // SPECIAL CASES...

      // Missing COLOR property!
      HEAD SEGMENT: group = { level: 1, id: root~car, groupBy: type }
      OPEN SEGMENT: group = { level: 1, id: root~car, groupBy: type }

        HEAD SEGMENT: group = { level: 2, id: root~car~work, groupBy: variant }
        OPEN SEGMENT: group = { level: 2, id: root~car~work, groupBy: variant }

          ITEM SEGMENT: { id: 16, type: car, color: '', variant: work, description: C6 }

        CLOSE SEGMENT: group = { id: root~car~work }

      CLOSE SEGMENT: group = { id: root~car }


      // Missing TYPE + COLOR properties!
      HEAD SEGMENT: group = { level: 1, id: root~work, groupBy: variant }
      OPEN SEGMENT: group = { level: 1, id: root~work, groupBy: variant }

        ITEM SEGMENT: { id: 17, type: '', color: '', variant: work, description: A1 }

      CLOSE SEGMENT: group = { id: root~work }


      // Missing COLOR + VARIANT properties!
      HEAD SEGMENT: group = { level: 1, id: root~car, groupBy: type }
      OPEN SEGMENT: group = { level: 1, id: root~car, groupBy: type }

        ITEM SEGMENT: { id: 18, type: 'car', color: '', variant: '', description: C7 }

      CLOSE SEGMENT: group = { id: root~car }


      // ZERO GROUPING properties! (i.e. In ROOT GROUP)
      ITEM SEGMENT: { id: 19, type: '', color: '', variant: '', description: B1 }


    ROOT CLOSE SEGMENT: group = { id: root }




ListGroupsUi
------------

  construct ( $listItems, $groupByProps, $aggregates = [] )

  // Array of ListUiSegement objects then we need to render
  // in sequence to construct the list UI
  $uiSegments = []

  // The group that holds to TOP-LEVEL list items.
  // = new ListUiGroup('root');
  $rootGroup

  // Provide indexes to items and groups on the TOP LEVEL.
  $topLevelListIndex = 1

  getListIndex ( $listGroup )

  getItemGroup ( $item, array $groupByProps )

  onSameBranch ( $group1, $group2 )

  updateGroupAggregates ( $currentGroup, $item, $aggregateTypes, $aggregates )

  closeAllLevelsFromTo ( $currentGroup, $targetGroup, $item = null )

  openNewLevelsFromTo ( $currentGroup, $targetGroup, $item, $groupByProps )

  generateUiSegments ( $listItems, $groupByProps, $aggregates )



ListUiGroup
-----------

  construct ( $idParts = [], $level = 0, $groupByProp = null, $parentGroup = null )

  $id                 // id = 'root~flower~red' or 'root~12~28' depending on values of grouping properties.
                      // id is a STRING of incremental indexes that make this group UNIQUE
                      // NOT a STRING that makes sense in terms of visual placement like
                      // $listIndex!

  $level              // Level of THIS GROUP. ROOT GROUP LEVEL = 0;

  $idParts            // idParts = ['root', 'flower', 'red'] or ['root', '12', '28']
                      // We can get the grouping property values from this array!
                      // Say the grouping property is: color_id, then we can get the color id's

  $groupByProp        // What ITEM PROPERTY we want to group by! (e.g color)

  $parentGroup        // Unless THIS GROUP is THE ROOT GROUP (level:0, id:root, parentGroup: NULL),
                      // this value will the parentGroup of THIS GROUP!

  $itemCount = 0      // The number of items in this group

  $listIndex = 0      // STRING representing the VISUAL numbering and index of this group
                      // relative to the ENTIRE LIST.
                      // NOTE: $listIndex is different from $group->id or $item->id!
                      // e.g. [12.4].1, [12.4].2, [12.4].3, ... listIndex = 12.4

  $aggregates = []



ListUiSegment:
--------------

  construct ( $type, $item, $group, $itemGroupIndex = null )

  $type                // Segment Type (i.e. headerSegment, openSegment, ...)

  $item                // Segment Data Item Obj

  $item->_index        // The natural (1,2,3,..) NON INDENTED index of THIS ITEM relative
                       // to the ENTIRE LIST. (_index is independent of grouping!)
                       // Use when you DON'T WANT:
                       //    "1. Item 1"
                       //       "1.1. Item 2"
                       //       "1.2. Item 3"
                       //    "2. Item 4"
                       //    ...
                       // But rater want:
                       //    "1. Item 1"
                       //       "2. Item 2"
                       //       "3. Item 3"
                       //    "4. Item 4"
                       //    ...

  $group               // Segment ListUiGroup Obj

  $itemGroupIndex      // The index of THIS ITEM inside its group.
                       // e.g. "1.2.[1]", "2.[3]", "4.1.[1]", ...
                       // itemGroupIndex = 1, 3, 1, ...

  getItemListNumber () // Generates a STRING with THIS ITEM's visual list number.
                       // $group->listIndex + $itemGroupIndex
                       // e.g. "1.2.1", "2.3", "4.1.1", ...



ListUiSegment Instance Examples:
--------------------------------

  [1] => OneFile\ListUiSegment Object
  (
    [type] => openSegment
    [item] => stdClass Object
      (
        [id] => 1538
        [seq] => 0
        [pakket_id] => 51
        [uitstallingtipe_id] => 9
        [areatipe_id] => 6
        [opsietipe_id] => 2
        [opsiesubtipe_id] => 0
        [uitstalopsie_id] => 132
        [vormVeld] => OneFile\FormFieldModel Object
        ...
        [_index] => 1
      )
    [group] => OneFile\ListUiGroup Object
      (
        [id] => root~2
        [level] => 1
        [idParts] => Array
          (
            [0] => root
            [1] => 2
          )
        [groupByProp] => opsietipe_id
        [parentGroup] => OneFile\ListUiGroup Object
          (
            [id] => root
            [level] => 0
            [idParts] => Array
              (
                [0] => root
              )
            [groupByProp] =>
            [parentGroup] =>
            [itemCount] => 7
            [listIndex] => 0
            [aggregates] => Array
              (
              )
          )
        [itemCount] => 2
        [listIndex] => 1
        [aggregates] => Array
          (
            [count] => 2
          )
      )
    [itemGroupIndex] => 0
  )

  ...

  [39] => OneFile\ListUiSegment Object
  (
    [type] => itemSegment
    [item] => stdClass Object
      (
        [id] => 1602
        [seq] => 0
        [pakket_id] => 51
        [uitstallingtipe_id] => 9
        [areatipe_id] => 6
        [opsietipe_id] => 5
        [opsiesubtipe_id] => 3
        [uitstalopsie_id] => 145
        ...
        [_index] => 18
      )
    [group] => OneFile\ListUiGroup Object
      (
        [id] => root~5~3
        [level] => 2
        [idParts] => Array
          (
            [0] => root
            [1] => 5
            [2] => 3
          )
        [groupByProp] => opsiesubtipe_id
        [parentGroup] => OneFile\ListUiGroup Object
          (
            [id] => root~5
            [level] => 1
            [idParts] => Array
              (
                [0] => root
                [1] => 5
              )
            [groupByProp] => opsietipe_id
            [parentGroup] => OneFile\ListUiGroup Object
              (
                [id] => root
                [level] => 0
                [idParts] => Array
                  (
                    [0] => root
                  )
                [groupByProp] =>
                [parentGroup] =>
                [itemCount] => 7
                [listIndex] => 0
                [aggregates] => Array
                  (
                  )
              )
            [itemCount] => 3
            [listIndex] => 4
            [aggregates] => Array
              (
              )
          )
      [itemCount] => 3
      [listIndex] => 4.2
      [aggregates] => Array
        (
          [count] => 3
        )
    )
    [itemGroupIndex] => 3
  )



ListUiGroup Instance Examples:
------------------------------

  [group] => OneFile\ListUiGroup Object

  (
      [id] => root~4~5
      [level] => 2
      [idParts] => Array
          (
              [0] => root
              [1] => 4
              [2] => 5
          )

      [groupByProp] => opsiesubtipe_id
      [parentGroup] => OneFile\ListUiGroup Object
          (
              [id] => root~4
              [level] => 1
              [idParts] => Array
                  (
                      [0] => root
                      [1] => 4
                  )

              [groupByProp] => opsietipe_id
              [parentGroup] => OneFile\ListUiGroup Object
                  (
                      [id] => root
                      [level] => 0
                      [idParts] => Array
                          (
                              [0] => root
                          )

                      [groupByProp] =>
                      [parentGroup] =>
                      [itemCount] => 3
                      [listIndex] => 0
                      [aggregates] => Array
                          (
                          )

                  )

              [itemCount] => 3
              [listIndex] => 3
              [aggregates] => Array
                  (
                      [count] => 3
                  )

          )

      [itemCount] => 0
      [listIndex] => 3.4
      [aggregates] => Array
          (
          )

  )

  [group] => OneFile\ListUiGroup Object
  (
      [id] => root~4~6
      [level] => 2
      [idParts] => Array
          (
              [0] => root
              [1] => 4
              [2] => 6
          )

      [groupByProp] => opsiesubtipe_id
      [parentGroup] => OneFile\ListUiGroup Object
          (
              [id] => root~4
              [level] => 1
              [idParts] => Array
                  (
                      [0] => root
                      [1] => 4
                  )

              [groupByProp] => opsietipe_id
              [parentGroup] => OneFile\ListUiGroup Object
                  (
                      [id] => root
                      [level] => 0
                      [idParts] => Array
                          (
                              [0] => root
                          )

                      [groupByProp] =>
                      [parentGroup] =>
                      [itemCount] => 3
                      [listIndex] => 0
                      [aggregates] => Array
                          (
                          )

                  )

              [itemCount] => 4
              [listIndex] => 3
              [aggregates] => Array
                  (
                      [count] => 3
                  )

          )

      [itemCount] => 0
      [listIndex] => 3.5
      [aggregates] => Array
          (
          )

  )