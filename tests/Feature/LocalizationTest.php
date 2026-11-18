<?php

declare(strict_types=1);

it('redirects the root url to the default locale when the browser language is not supported', function (): void {
    // The test client always sends an Accept-Language header, so we send an unsupported one.
    $this->get('/', ['Accept-Language' => 'fr'])
        ->assertRedirect(route('frontend.home', ['locale' => 'bn']));
});

it('falls back to the configured default locale, not to the first listed locale', function (): void {
    config(['app.locale' => 'en']);

    $this->get('/', ['Accept-Language' => 'fr'])
        ->assertRedirect(route('frontend.home', ['locale' => 'en']));
});

it('uses the Accept-Language header to choose a supported locale', function (): void {
    $this->get('/', ['Accept-Language' => 'en'])
        ->assertRedirect(route('frontend.home', ['locale' => 'en']));
});

it('prefers the locale remembered in the session over the browser header', function (): void {
    $this->withSession(['locale' => 'en'])
        ->get('/', ['Accept-Language' => 'bn'])
        ->assertRedirect(route('frontend.home', ['locale' => 'en']));
});

it('renders the home page in Bangla', function (): void {
    $this->get('/bn')
        ->assertOk()
        ->assertSee('আপনার সুপার শপে স্বাগতম')
        ->assertSee('lang="bn"', false);
});

it('renders the home page in English', function (): void {
    $this->get('/en')
        ->assertOk()
        ->assertSee('Welcome to your Super Shop')
        ->assertSee('lang="en"', false);
});

it('stores the visited locale in the session', function (): void {
    $this->get('/en')->assertSessionHas('locale', 'en');
});

it('returns 404 for an unsupported locale', function (): void {
    $this->get('/fr')->assertNotFound();
});

it('links the language switcher to the same page in the other language', function (): void {
    $this->get('/bn')->assertSee('href="' . url('/en') . '"', false);
});

it('exposes the framework health check', function (): void {
    $this->get('/up')->assertOk();
});
