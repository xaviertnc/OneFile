/*globals window, debug */

if (!Array.prototype.indexOf) //IE8 does not support "indexOf" 
{
	Array.prototype.indexOf = function(obj, start)
	{
	     for (var i = (start || 0), j = this.length; i < j; i++)
	     {
	         if (this[i] === obj) { return i; }
	     }
	     return -1;
	};
}


var lib = {

	isset: function(value)
	{
		return (typeof value !== "undefined");
	},

	empty: function(value)
	{
		return ( ! value);
	},

	isnull: function(value)
	{
		return (value === null);
	},

	lpad: function(str,len,pad)
	{
		if (!pad) { pad = "0"; }
		if (typeof str === "number") { str = str.toString(); }
		if (len > str.length)
		{
			return new Array(len + 1 - str.length).join(pad) + str;
		}
		else
		{
			return str;
		}
	},
	
	intersectRect: function(r1,r2)
	{
		return !(r2.px > (r1.px+r1.w)||
				(r2.px+r2.w) < r1.px ||
				r2.py > (r1.py+r1.h) ||
				(r2.py+r2.h) < r1.py);
	},
	
	getTime: function() //Get the best timestamp depending on browser capabilities!
	{
		if(window.performance.now)
		{
		    return window.performance.now();
		} 
		else
		{
		    if(window.performance.webkitNow)
		    {
		        return window.performance.webkitNow();
		    }
		    else
		    {
		        return new window.Date().getTime();
		    }
		}
	}

};
