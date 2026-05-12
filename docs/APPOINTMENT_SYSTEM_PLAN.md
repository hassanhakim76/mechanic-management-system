# Appointment System Plan

## Purpose

This document is the planning reference for adding an appointment system inside the autoshop application.

The goal is to make appointments part of the shop workflow, not just calendar events. Appointments should connect to customers, vehicles, mechanics, work orders, inspections, and shop history.

## Recommended Direction

The application should be the source of truth for appointments.

Google Calendar can be considered later as an optional sync/export feature, but the internal appointment system should control the real shop workflow.

## Main Workflow

### 1. Customer Call

Frontdesk opens the Appointment screen and searches by:

- Phone
- Cell
- Customer name
- Plate
- VIN

If an existing customer or vehicle is found, the app should show:

- Customer information
- Vehicle list
- Open work orders
- Last visit
- Appointment history
- No-show or cancelled appointment history

If no match is found, frontdesk can create a new customer and vehicle quickly.

### 2. Create Appointment

Frontdesk selects:

- Customer
- Vehicle
- Appointment date
- Appointment time
- Appointment type or reason
- Estimated duration
- Priority
- Optional mechanic
- Notes from the call

Example:

```text
Customer: John Smith
Vehicle: 2018 Honda Civic / FAAY159
Date: 2026-05-12
Time: 9:00 AM
Reason: Noise from front brakes
Duration: 60 minutes
Status: BOOKED
```

### 3. Confirm Appointment

The appointment starts as:

```text
BOOKED
```

If the shop confirms with the customer, the status can become:

```text
CONFIRMED
```

### 4. Customer Arrives

On the appointment day, frontdesk uses the daily appointment board.

When the customer arrives:

```text
BOOKED or CONFIRMED -> ARRIVED
```

### 5. Create Work Order

After arrival, frontdesk clicks:

```text
Create Work Order
```

The app creates a real work order using appointment data:

- `CustomerID`
- `CVID`
- Mileage, if entered
- Work required
- Customer note
- Priority
- Mechanic, if assigned

The appointment stores the linked `WOID`.

### 6. Mechanic Work

After the work order exists, the normal mechanic flow takes over:

- New work order queue
- Pending assigned jobs
- Work order detail
- Photos
- Inspection
- Action taken

### 7. Complete Appointment

When the linked work order is finished, the appointment can become:

```text
COMPLETED
```

The appointment remains visible in customer and vehicle history.

## Appointment Statuses

Recommended statuses:

```text
BOOKED
CONFIRMED
ARRIVED
IN_PROGRESS
COMPLETED
CANCELLED
NO_SHOW
RESCHEDULED
```

Recommended main lifecycle:

```text
BOOKED -> CONFIRMED -> ARRIVED -> IN_PROGRESS -> COMPLETED
```

Other paths:

```text
BOOKED -> CANCELLED
BOOKED -> NO_SHOW
BOOKED -> RESCHEDULED -> BOOKED
ARRIVED -> IN_PROGRESS
```

## Who Can Make Appointments

### Admin

Admin can:

- Create appointments
- Edit appointments
- Cancel appointments
- Reschedule appointments
- Mark arrived
- Mark no-show
- Create work orders from appointments
- Assign mechanics
- Override full schedules
- Manage appointment settings

### Frontdesk

Frontdesk should be the main appointment user.

Frontdesk can:

- Create appointments
- Edit appointments
- Cancel appointments
- Reschedule appointments
- Mark arrived
- Mark no-show
- Create work orders from appointments
- Search customer and vehicle records

Frontdesk should not manage system-level appointment settings unless the business wants that.

### Mechanic

Mechanic can:

- View today's appointments
- View appointments assigned to them
- See upcoming work
- Open linked work orders

Mechanics should not create, cancel, or reschedule appointments by default.

### Customer

Customer booking should not be included in version 1.

Future online booking should create appointment requests, not confirmed appointments.

## Duration Estimate

Duration is the calendar/shop slot time, not final labor time.

Version 1 should allow manual duration selection:

```text
15 minutes
30 minutes
45 minutes
60 minutes
90 minutes
120 minutes
Half day
Full day
```

Later, appointment types can provide suggested defaults:

```text
Oil change              30 minutes
Tire swap               60 minutes
Brake inspection        60 minutes
Diagnostic              60 minutes
Pre-purchase inspection 90 minutes
Safety inspection       120 minutes
Alignment               60 minutes
```

Frontdesk should always be able to override the suggested duration.

## Available Slot Logic

The app should find available slots based on:

- Selected date
- Shop hours
- Closed days
- Existing appointments
- Estimated duration
- Shop capacity
- Mechanic availability later
- Bay/resource availability later

Example:

```text
Shop hours:
8:00 AM - 5:00 PM
Lunch:
12:00 PM - 1:00 PM

Existing appointments:
8:00 - 8:30
9:00 - 10:00
10:30 - 11:30

Requested duration:
60 minutes

Available:
1:00 PM
2:00 PM
3:00 PM
4:00 PM
```

Version 1 should use simple shop-wide capacity.

Example:

```text
Maximum overlapping appointments: 2
```

A slot is available if fewer than 2 active appointments overlap the requested time range.

Later versions can add mechanic capacity, bay capacity, or service-specific capacity.

## Database Direction

The appointment system should mostly add new tables.

Existing tables should remain unchanged for version 1 if possible.

Recommended minimum tables:

```text
appointments
appointment_status_log
```

Future tables:

```text
appointment_services
shop_hours
shop_closed_days
mechanic_availability
appointment_settings
```

Recommended appointment links:

```text
appointments.CustomerID -> customers.CustomerID
appointments.CVID      -> customer_vehicle.CVID
appointments.WOID      -> work_order.WOID
```

Do not add `AppointmentID` to `work_order` in version 1 unless there is a strong reason.

Preferred relationship:

```text
appointments.WOID
```

This lets the appointment point to the work order after it is created while keeping the current work order table stable.

## Suggested App Screens

### Daily Appointment Board

This should be the first screen built.

It should show:

- Time
- Customer
- Vehicle
- Plate
- Reason
- Duration
- Status
- Mechanic
- Linked work order, if any

### Appointment Create/Edit

Fields:

- Customer search
- Vehicle selection
- Date
- Time
- Duration
- Reason/type
- Notes
- Mechanic assignment
- Priority

### Appointment Detail

Actions:

- Confirm
- Mark arrived
- Reschedule
- Cancel
- Mark no-show
- Create work order
- Open linked work order

### Customer/Vehicle History

Show previous:

- Appointments
- No-shows
- Cancelled appointments
- Linked work orders

## Suggested First Version

Phase 1 should stay small and useful:

- Add appointment tables
- Add daily appointment board
- Add create/edit appointment page
- Add available slot suggestions
- Add appointment status log
- Add create work order from appointment
- Allow Admin and Frontdesk to manage appointments
- Allow Mechanics to view relevant appointments

## Future Versions

### Phase 2

- Weekly calendar view
- Appointment type defaults
- Shop hours settings
- Closed day settings
- Better reschedule history

### Phase 3

- Mechanic availability
- Capacity by mechanic
- Capacity by bay/resource
- Appointment reminders
- SMS/email integration

### Phase 4

- Online appointment requests
- Customer confirmation links
- Google Calendar optional sync/export

## Important Design Rules

- Keep appointments separate from work orders until the customer arrives or the shop decides to create the work order.
- Keep appointment history even if the appointment is cancelled or no-show.
- Do not delete appointments in normal workflow.
- Use status changes instead of hard deletes.
- Log every important status change.
- Keep the appointment system simple at first.

## Open Decisions

- Should appointment slots be every 15 minutes or every 30 minutes?
- Should lunch block scheduling?
- Should frontdesk assign mechanic during booking or only after arrival?
- Should appointment types be fixed settings or free text at first?
- Should no-show count appear on customer profile?
- Should appointments reserve shop-wide capacity only, or mechanic capacity from the start?

