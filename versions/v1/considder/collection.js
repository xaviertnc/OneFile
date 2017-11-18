/*globals debug */

/**
 * JS Collection Class
 *  - Combines Array and ObjectProperty type Entity Lists into ONE class.
 *  - Should remove all complexities and differences in accessing list items using either Arrays or Object Properties
 *
 * Note:
 *	- If the initial items collection is an Object of objects,
 *		the parent object will be converted into a plain array of Objects.
 *		If the items are also objects and the "addItemIDs" option is TRUE, IDs will be added to each
 *		item with the same value as the parent object's property name for that item.
 *
 *	- If collection items are objects,
 *		getFirst() or getWhere() can be used to fetch one or mulitple items by item property value.
 *		read(itemIndex, propertyToRead, default) or readyWhere(valueToMatch, propertyToSearch, propertyToRead, default) can be used
 *		to read a specific item's property values
 *
 *	- If collection items are scalar values,
 *		get(itemIndex, default) can be used to read an item / value
 *
 * @author: C. Moller 04 Nov 2014 <xavier.tnc@gmail.com>
 * 
 * @param id Collection ID String or Item ID in case this collection is an item in another collection
 * @param items Array of Entity Objects
 * @returns Collection
 */
var Collection = function(id, items)
{
	this.id = id;
	this.items = items || [];
};


Collection.prototype.size = function()
{
	return this.items.length;
};


Collection.prototype.add = function(item)
{
	this.items.push(item);
};


Collection.prototype.init = function(initialItems, itemConfigFunc, addItemIDs, idPropertyName)
{
	var collection = this;

	if ( ! initialItems) return;

	addItemIDs = addItemIDs || false;

	idPropertyName = idPropertyName || 'id';

	debug.log('Collection.init(), initialItems.type = ' + typeof initialItems + '. Convert Object to Array. Add IDs = ' + debug.yesNo(addItemIDs));


	// First convert Assoc Array type Object to plain Array of Objects
	if (typeof initialItems === "object")
	{		
		$.each(initialItems, function(id, item)
		{
			collection.add(item);
		});
	}
	else
	{
		collection.items = initialItems;
	}

	// itemConfigFunc() Note:
	//	The index and item parameter order is reversed from what is required by $.each() so we can ignore the "index" if not required!
	//	This function configures each collection item and MUST RETURN the same item or some modified version of it.
	//	
	//	Example uses:
	//	  Mutate property values to suit current view requirements.
	//	  Add calculated properties not present in the initial data.
	//	  Validations etc.

	if ( typeof itemConfigFunc === "function" )
	{
		$.each(collection.items, function(index, item)
		{
			// Add an ID property to each item if "addItemIDs == true" and the item is an Object with an "undefined" ID property
			if (addItemIDs && ["object", "function"].indexOf(typeof item) >= 0 && typeof item[idPropertyName] === "undefined")
			{
				item[idPropertyName] = initialItems[index];
			}

			collection.items[index] = itemConfigFunc(item, index);
		});
	}
};


Collection.prototype.remove = function(item)
{
	delete this.items[item];
};


Collection.prototype.get = function(itemIndex, defaultValue)
{
	// debug.log('Collection::get(), itemIndex:' + itemIndex + ', defaultValue:' + defaultValue);

	if (typeof itemIndex === "undefined" || itemIndex === "all")
	{
		return this.items;
	}
	else
	{
		var item = this.items[itemIndex];

		return (typeof item !== "undefined") ? item : defaultValue;
	}
};


Collection.prototype.getWhere = function(valueToMatch, propertyToSearch)
{
	var results = [];
	var propertyValue;

	propertyToSearch = propertyToSearch || 'id';

	for (var i = 0, j = this.items.length; i < j; i++)
	{
		propertyValue = this.items[i][propertyToSearch];

		// debug.log('Collection::getWhere(), this.items[ ' + i + '][' + searchProperty + '] = ' + propertyValue);

		if (propertyValue === valueToMatch)
		{
			results.push(this.items[i]);
		}
	}

	return results;
};


Collection.prototype.getFirst = function(valueToMatch, propertyToSearch, defaultValue)
{
	var items = this.getWhere(valueToMatch, propertyToSearch);

	defaultValue = defaultValue || null;

	return items.length ? items[0] : defaultValue;
};


Collection.prototype.all = function(defaultValue)
{
	return this.get('all', defaultValue);
};


Collection.prototype.read = function(itemIndex, propertyToRead, defaultValue)
{
	var item = this.get(itemIndex);
	return (! item || typeof item[propertyToRead] === "undefined") ? defaultValue : item[propertyToRead];
};


Collection.prototype.readWhere = function(valueToMatch, propertyToSearch, propertyToRead, defaultValue)
{
	var item = this.getFirst(valueToMatch, propertyToSearch);
	return (! item || typeof item[propertyToRead] === "undefined") ? defaultValue : item[propertyToRead];
};