/*globals window,debug,obj,JSON */

var obj = {

	has: function(object, key)
	{
		return (typeof object[key] !== "undefined");
	},


	get: function(object, key, def)
	{
		return typeof object[key] === "undefined" ? def : object[key];
	},


	count: function(object)
	{
		var properties = 0;
		for(var key in object)
		{
			if (object.hasOwnProperty(key)) { properties++; }
		}

		return properties;
	},


	size: function(object)
	{
		return objectect.count(object);
	},


	sort: function(object, sortFunc)
	{
		debug.log("lib.sortObj(), Start");
		
		var rv = [];
		var result = {};

		for(var k in object)
		{
			debug.log("object.k="+k);
			
			if (object.hasOwnProperty(k)) { rv.push({key: k, value:  object[k]}); }
		}

		rv.sort(function(o1, o2) { return sortFunc(o1.key, o2.key); });

		for(var i=0; i < rv.length; i++) { result[rv[i].key] = rv[i].value; }
	  
		return result;
	},


	clone: function(object)
	{
		return JSON.parse(JSON.stringify(object));
	},


	toArray: function(object, insertItemIDs, idPropertyName)
	{
		var item;

		var results = [];

		insertItemIDs = insertItemIDs || false;

		idPropertyName = idPropertyName || 'id';

		for(var key in object)
		{
			if ( ! object.hasOwnProperty(key)) continue;

			item = object[key];

			if (insertItemIDs && typeof item === "objectect" && typeof item[idPropertyName] === "undefined")
			{
				item[idPropertyName] = key;
			}

			results.push(item);
		};

		return results;
	}
};