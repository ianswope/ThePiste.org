<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThePiste: Plan a fencer's season that adds up</title>
    <meta name="description" content="A personalized USA Fencing season planner: set a goal, build the schedule that serves it, and track results toward your rating.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="lnav">
    <a class="brand" href="{{ url('/') }}">THE<span>PISTE</span></a>
    <div class="links">
        <a href="{{ route('demo') }}">Sample</a>
        <a href="{{ url('/login') }}">Sign in</a>
        <a class="btn btn-primary" style="padding:8px 16px;" href="{{ url('/register') }}">Get started</a>
    </div>
</div>

<section class="hero">
    <div class="hero-inner">
        <div class="light l" aria-hidden="true"></div>
        <div class="hero-main">
            <div class="hero-eye">USA Fencing · Season Planner</div>
            <h1>Build a season that <span class="hl">actually adds up</span>.</h1>
            <p class="lead">
                Every season is the same scramble: which tournaments matter, what's driveable, what collides,
                and what it all costs. ThePiste turns your fencer's goal into a prioritized, personalized schedule,
                then tracks the results that get you there.
            </p>
            <div class="hero-cta">
                <a class="btn btn-primary btn-lg" href="{{ url('/register') }}">Build your season</a>
                <a class="btn btn-ghost btn-lg" href="{{ route('demo') }}">See a sample &rarr;</a>
            </div>
        </div>
        <div class="light r" aria-hidden="true"></div>
    </div>
</section>

<section class="section" aria-label="How it works">
    <div class="section-eye">The loop</div>
    <h2>Set the goal. Build the season. Track the results.</h2>
    <div class="loop">
        <div class="loopcard">
            <div class="num">01</div>
            <h3>Set the goal</h3>
            <p>Earn a B, qualify for Junior Olympics, build regional standing. The goal drives every recommendation that follows.</p>
        </div>
        <div class="loopcard">
            <div class="num">02</div>
            <h3>Build the season</h3>
            <p>Lock the non-negotiables, add the best-value events, resolve weekend clashes, and see drives, flights, and cost add up as you go.</p>
        </div>
        <div class="loopcard">
            <div class="num">03</div>
            <h3>Track the results</h3>
            <p>Log finishes and points after each event and watch a clear meter close in on the rating you're chasing.</p>
        </div>
    </div>
</section>

<section class="section" aria-label="What it does">
    <div class="section-eye">Built for the real decisions</div>
    <h2>The judgment a seasoned fencing parent makes, computed for you.</h2>
    <div class="feat">
        <div class="item"><span class="tick">✓</span><div><b>Eligibility, filtered</b><span>Only the events your fencer can actually enter, by weapon, age category, and rating.</span></div></div>
        <div class="item"><span class="tick">✓</span><div><b>Drive vs fly, from your ZIP</b><span>Real distance from home decides what's a road trip and what needs a flight.</span></div></div>
        <div class="item"><span class="tick">✓</span><div><b>Weekend conflict detection</b><span>When two events collide, the higher-value one wins and the other is flagged.</span></div></div>
        <div class="item"><span class="tick">✓</span><div><b>NAC anchors &amp; regional value</b><span>National events anchor the year; the best in-region weekends fill it in.</span></div></div>
        <div class="item"><span class="tick">✓</span><div><b>Results &amp; rating progress</b><span>Log how it went and see how close you are to the goal.</span></div></div>
        <div class="item"><span class="tick">✓</span><div><b>Shareable &amp; exportable</b><span>Send the plan to a coach or push it to your calendar. FIE events coming soon.</span></div></div>
    </div>
</section>

<div class="cta-band">
    <div class="inner">
        <h2>Plan the whole season in an afternoon.</h2>
        <p>Free to start. Build a profile, get your calendar, and lock the dates that matter.</p>
        <a class="btn btn-primary btn-lg" href="{{ url('/register') }}">Get started</a>
    </div>
</div>

<div class="lfoot">
    <span>ThePiste · USA Fencing season planning</span>
    <span><a href="{{ route('demo') }}">View a sample season</a></span>
</div>

</body>
</html>
