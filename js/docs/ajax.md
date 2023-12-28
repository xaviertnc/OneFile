## Basic Guide to Using Ajax JS

### Initialize
```javascript
const ajax = new F1.modules.Ajax();
```

### GET Request
```javascript
ajax.fetch('/api/getData')
  .then(data => console.log(data))
  .catch(err => console.error(err));
```

### POST Request
```javascript
ajax.post('/api/postData', { key: 'value' })
  .then(data => console.log(data))
  .catch(err => console.error(err));
```

### Form Submit
```javascript
const form = document.getElementById('myForm');
ajax.submit(form)
  .then(data => console.log(data))
  .catch(err => console.error(err));
```