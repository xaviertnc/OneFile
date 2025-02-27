# F1\DateTime Class

---

```php
// now
$appDateTime = new F1\DateTime('2023-12-25 15:00:00', 'Africa/Johannesburg');
echo $appDateTime->now(); // Outputs: 2023-12-25 15:00:00

// mock
$appDateTime = new F1\DateTime();
$appDateTime->mock('2023-12-31 23:59:59', 'UTC');
echo $appDateTime->now(); // Outputs: 2023-12-31 23:59:59 UTC
$appDateTime->mock('+1 day', 'America/New_York');
echo $appDateTime->now(); // Outputs: 2024-01-01 [time adjusted to EST]

// reset
$appDateTime->reset();
echo $appDateTime->now(); // Outputs current time in the default PHP timezone

// time
$appDateTime = new F1\DateTime();
echo $appDateTime->time(); // Outputs: Current Unix timestamp

// date
$appDateTime = new F1\DateTime('2023-12-25');
echo $appDateTime->date('l'); // Outputs: Monday (Day of the week)

// strtotime
$appDateTime = new F1\DateTime();
$timestamp = $appDateTime->strtotime('+1 day'); // Equivalent to PHP's strtotime('+1 day')
echo date('Y-m-d', $timestamp); // Outputs: [Tomorrow's date]

// modify & format
$appDateTime = new F1\DateTime('2023-12-25 10:00:00', 'UTC');
$appDateTime->modify('+1 day');
echo $appDateTime->format('Y-m-d'); // Outputs: 2023-12-26
echo $appDateTime->format('Y'); // Outputs: 2023
echo $appDateTime->year(); // Outputs: 2023

// Set
$appDateTime = new F1\DateTime('2023-01-01');
$appDateTime->setDate(2025, 12, 25);
$appDateTime->setTime(15, 30, 45);
echo $appDateTime->format('Y-m-d'); // Outputs: 2025-12-25
echo $appDateTime->format('H:i:s'); // Outputs: 15:30:45
$appDateTime->setTimestamp(1672444800);
echo $appDateTime->format('Y-m-d'); // Outputs: 2022-12-31

// Retrieve
echo $appDateTime->getTimestamp(); // Outputs: Unix timestamp

// Compare
$anotherDate = new F1\DateTime('2023-12-31 10:00:00');
$diff = $appDateTime->diff($anotherDate);
echo $diff->format('%a days'); // Outputs: 5 days
$date1 = new F1\DateTime('2023-12-25');
$date2 = new F1\DateTime('2023-12-31');
$diff = $date1->diff($date2);
echo $diff->format('%R%a days'); // Outputs: +6 days

// Add
$appDateTime = new F1\DateTime('2023-12-25');
$appDateTime->add(new DateInterval('P1M')); // Adds 1 month
echo $appDateTime->format('Y-m-d'); // Outputs: 2024-01-25

// Subtract
$appDateTime = new F1\DateTime('2023-12-25');
$appDateTime->sub(new DateInterval('P10D')); // Subtracts 10 days
echo $appDateTime->format('Y-m-d'); // Outputs: 2023-12-15

// Timezone
$appDateTime->setTimezone(new DateTimeZone('America/New_York'));
echo $appDateTime->format('Y-m-d H:i:s T'); // Outputs: Time in EST
$appDateTime = new F1\DateTime('2023-12-25 10:00:00', 'UTC');
echo $appDateTime->getTimezone()->getName(); // Outputs: UTC
echo $appDateTime->format('Y-m-d H:i:s T'); // Outputs: 2023-12-25 05:00:00 EST

// Create From
$appDateTime = F1\DateTime::createFromFormat('Y-m-d', '2023-12-01');

// __toString
$appDateTime = new F1\DateTime('2023-12-25 10:00:00');
echo $appDateTime; // Outputs: 2023-12-25 10:00:00

```

---