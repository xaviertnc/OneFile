/* global F1 */

/* ajax.js */

(function(F1) {

  /**
   * F1 Ajax Class - 12 Oct 2023
   * 
   * This class is a bit overkill ( i.e. unnecessary ;), but it's a nice reminder on how to use
   * the Fetch API and how to handle various content types and response types.
   * 
   * 
   * @author  C. Moller <xavier.tnc@gmail.com>
   * @version 3.2 - FIX - 11 Jan 2024
   *   - Return the response "as is" if we set the "ResponseType" to anything other 
   *     than "json", "html" or "text". This allows us to use "await Ajax.fetch..."!
   * 
   * 
   * Method: fetch
   *  GET data from, or POST data to, a specified url via async HTTP.
   *  Supports various body content types including form-data, urlencoded, and json.
   *  - If contentType is undefined and data is an object, contentType defaults to 'urlencoded'.
   *  - If data is FormData, URLSearchParams or String, fetch API auto detects and sets the type.
   *  - If responseType is set to 'json', a result object is returned instead of a string.
   * 
   * Method: post
   *  Send data via POST/PUT request.
   * 
   * Method: submit
   *  Submits a form using the POST method.
   *  FormData object is used to gather form data.
   *  Remember to add name attrs to your inputs, or FormData won't include them.
   *  
   * Content-Type Pros and Cons:
   *  "multipart/form-data" (form-data)
   *      Pros: Supports files, PHP auto-fills $_POST, supports nested FormData objects.
   *      Cons: High body overhead and complexity.
   *  "application/x-www-form-urlencoded" (urlencoded)
   *      Pros: Low body overhead and complexity, PHP auto-fills $_POST.
   *      Cons: Does not support nested objects, deep arrays, or files.
   *  "application/json" (json)
   *      Pros: Supports nested objects and arrays, medium body overhead and complexity.
   *      Cons: PHP does NOT auto-fill $_POST! We have to manually parse content using
   *            `$postData = json_decode(file_get_contents('php://input'), true);`
   *            Does not support files. 
   * 
   * NOTE: We don't handle soft errors here, only network errors! 
   *  Check the response for soft error info like { error: 'error message' }
   * 
   * Usage:
   * const data = await F1.lib.Ajax.fetch('https://example.com/api', { method: 'POST', data: { name: 'John' } });
   * const data = await F1.lib.Ajax.post('https://example.com/api', { id: 99 }, { responseType: 'text/html' });
   * const data = await F1.lib.Ajax.submit(document.querySelector('form#myForm'));
   */

  function log(...args) { if (F1.DEBUG > 1) console.log(...args); }

  class Ajax {

    static async fetch(url, opts = {}) {
      let { method = 'GET', headers = {}, data = null, contentType, responseType = 'json', ...rest } = opts;
      let body; const finalHeaders = { 'X-Requested-With': 'XMLHttpRequest', ...headers };
      // Force the content type to 'urlencoded', if data is a plain object, cause it plays nice with PHP.
      if (!contentType && !(data instanceof FormData) && typeof data === 'object') contentType = 'urlencoded';
      log('ajax.fetch()', { url, method, headers, data, contentType, responseType, ...rest });
      switch (contentType) {
        case 'form-data':
          body = data instanceof FormData ? data : new FormData();
          Object.entries(data).forEach(([key, value]) => body.append(key, value));
          break;
        case 'urlencoded':
          body = data instanceof URLSearchParams ? data : new URLSearchParams(data);
          break;
        case 'json':
          finalHeaders['Content-Type'] = 'application/json;charset=UTF-8';
          body = typeof data === 'object' ? JSON.stringify(data) : data;
          break;
        default:
          // Unknown or undefined content type.
          // Fetch API will auto detect and set the Content-Type header.
          body = data;
          break;
      }
      const fetchOptions = { method: method.toUpperCase(), headers: finalHeaders, ...rest };
      if (fetchOptions.method !== 'GET' && fetchOptions.method !== 'HEAD') fetchOptions.body = body;
      try {
        const response = await fetch(url, fetchOptions);
        if (!response.ok) throw new Error(`HTTP error: ${response.status}, message: ${response.statusText}`);
        if (responseType === 'json') return await response.json();
        if ( responseType === 'html' || responseType === 'text' ) return await response.text();
        return response;
      } catch (err) {
        if (err instanceof TypeError) err.message = `Network error or CORS issue: ${err.message}`;
        console.error(`ajax.${method}.error:`, err);
        throw err;
      }
    }

    static post(url, data, options = {}) {
      return this.fetch(url, { method: 'POST', data, ...options });
    }

    static submit(form, opts = {}) {
      const data = new FormData(form);
      const { url = form.action || window.location.href, extraData, ...options } = opts;
      if (extraData) Object.entries(extraData).forEach(([key, value]) => data.append(key, value));
      return this.post(url, data, options);
    }
  }

  F1.lib = F1.lib || {};
  F1.lib.Ajax = Ajax;

})(window.F1 = window.F1 || {});
