MODEL_VALUE vs. VIEW_VALUE vs. DISPLAY_VALUE
=============================================
* MODEL_VALUE can be any data structure in the format it get's saved to DB or State.
* VIEW_VALUE can be any data structure in a format required by the current VIEW being rendered.
* DISPLAY_VALUE is the final string value returned by the VIEW RENDER FUNCTION.

Example (Field:PricePoints):
----------------------------
* PricePoints Model Value   = '[["value":100.00,"level":1],["value":"250","level":2]]'
* PricePoints View Value    =  [['value' => 100, 'level' => 1], ['value' => 250, 'level' => 2]]
* PricePoints Display Value = '<ul><li>R 100.00</li><li>R 250.00</li></ul>'

Model value and view value formats do NOT have to be different.

Model values or View values can be forwarded via Ajax to a client browser for processing and/or rendering
