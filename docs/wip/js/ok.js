var simpleButtons = new F1.lib.Popup({
    title: 'Simple Buttons',
    content: 'This popup has simple OK and Cancel buttons.',
    buttons: [
      { text: 'OK', onClick: function() { alert('OK clicked'); } },
      { text: 'Cancel', onClick: function() { simpleButtons.close(); } }
    ]
  });
simpleButtons.show();