<?php

use FlowAsia\Module4\Models\CompanyEvent;
use FlowAsia\Module4\Models\User;

function eventPayload(): array
{
    return ['title' => 'Company Town Hall', 'type' => 'town_hall', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-13', 'description' => 'All employees welcome.'];
}

function eventUser(string $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();

    return $user;
}

it('allows every HR role to create, list, update and delete events', function (string $role) {
    $user = eventUser($role);
    $this->actingAs($user)->postJson('/hr/events', eventPayload())->assertCreated();
    $event = CompanyEvent::firstOrFail();
    expect($event->created_by)->toBe($user->id);
    $this->getJson('/hr/events')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson('/hr/events/'.$event->id, array_replace(eventPayload(), ['title' => 'Updated Town Hall']))
        ->assertOk()->assertJsonPath('data.title', 'Updated Town Hall');
    $this->deleteJson('/hr/events/'.$event->id)->assertNoContent();
    $this->assertDatabaseMissing('company_events', ['id' => $event->id]);
})->with(['hr_admin', 'hr_staff', 'super_admin']);

it('blocks non-HR management without changing records', function (string $role) {
    $event = CompanyEvent::create(eventPayload());
    $this->actingAs(eventUser($role))->getJson('/hr/events')->assertForbidden();
    $this->postJson('/hr/events', eventPayload())->assertForbidden();
    $this->patchJson('/hr/events/'.$event->id, eventPayload())->assertForbidden();
    $this->deleteJson('/hr/events/'.$event->id)->assertForbidden();
    expect(CompanyEvent::count())->toBe(1);
    $this->assertDatabaseHas('company_events', ['id' => $event->id, 'title' => 'Company Town Hall']);
})->with(['employee', 'supervisor']);

it('requires authentication for both the feed and management', function () {
    $this->getJson('/calendar/events?start=2026-10-01&end=2026-11-01')->assertUnauthorized();
    $this->postJson('/hr/events', eventPayload())->assertUnauthorized();
});

it('rejects invalid dates, types, titles and client ownership or targeting', function (array $changes, string $field) {
    $this->actingAs(eventUser('hr_admin'))
        ->postJson('/hr/events', array_replace(eventPayload(), $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(CompanyEvent::count())->toBe(0);
})->with([
    'reversed dates' => [['ends_on' => '2026-10-11'], 'ends_on'],
    'invalid date' => [['starts_on' => '2026-02-30'], 'starts_on'],
    'unknown type' => [['type' => 'payroll'], 'type'],
    'blank title' => [['title' => '   '], 'title'],
    'creator spoof' => [['created_by' => 999], 'created_by'],
    'department targeting' => [['department_id' => 1], 'department_id'],
    'employee targeting' => [['employee_id' => 1], 'employee_id'],
]);

it('rejects an invalid update and preserves the original event', function () {
    $event = CompanyEvent::create(eventPayload());
    $this->actingAs(eventUser('hr_admin'))->putJson('/hr/events/'.$event->id, array_replace(eventPayload(), ['ends_on' => '2026-10-11']))
        ->assertUnprocessable();
    expect($event->fresh()->ends_on->toDateString())->toBe('2026-10-13');
});

it('returns overlapping events to every authenticated role with exclusive ends', function (string $role) {
    $event = CompanyEvent::create(array_replace(eventPayload(), ['starts_on' => '2026-09-30', 'ends_on' => '2026-10-01']));
    CompanyEvent::create(array_replace(eventPayload(), ['starts_on' => '2026-10-02', 'ends_on' => '2026-10-02']));
    $this->actingAs(eventUser($role))
        ->getJson('/calendar/events?start=2026-10-01T00:00:00%2B08:00&end=2026-10-02T00:00:00%2B08:00')
        ->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.id', 'company-event-'.$event->id)
        ->assertJsonPath('0.end', '2026-10-02')
        ->assertJsonPath('0.allDay', true)
        ->assertJsonPath('0.classNames.0', 'company-event')
        ->assertJsonPath('0.extendedProps.kind', 'company_event');
    expect($event->fresh()->ends_on->toDateString())->toBe('2026-10-01');
})->with(['employee', 'supervisor', 'hr_admin', 'hr_staff', 'super_admin']);

it('supports single-day events and rejects invalid feed bounds', function () {
    $this->actingAs(eventUser('hr_admin'))->postJson('/hr/events', array_replace(eventPayload(), ['ends_on' => '2026-10-12']))->assertCreated();
    $this->getJson('/calendar/events?start=2026-10-01&end=2026-11-01')->assertJsonPath('0.end', '2026-10-13');
    $this->getJson('/calendar/events?start=2026-11-01&end=2026-10-01')->assertUnprocessable();
    $this->getJson('/calendar/events')->assertUnprocessable();
});
