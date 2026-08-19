# AlefBayeSafar — Project Context

## 1. Project identity

Project name: **AlefBayeSafar**

Primary repository:

`https://github.com/hforouzi/alefbayesafar`

Base template:

`https://github.com/hforouzi/SymfonyAppTemplate`

The project must be created from the existing `SymfonyAppTemplate`, but the template repository itself must remain untouched. `alefbayesafar` is the real application repository from now on.

The Symfony template already provides the application foundation and must be reused instead of rebuilt:

- Symfony 7.2
- PHP 8.2+
- Doctrine ORM
- MySQL / MariaDB
- Authentication
- Users
- Roles
- Permissions
- Dynamic admin menus
- Settings
- Twig admin layout
- Localization
- Messenger
- HttpClient
- Mailer
- AssetMapper / ImportMap
- Stimulus
- Symfony UX
- PHPUnit
- PHPStan
- coding-standard tooling

The existing module-based architecture must be preserved.

Current template convention is roughly:

```text
src/
├── Modules/
│   ├── Auth/
│   ├── Default/
│   └── User/
├── Shared/
├── Service/
└── EventSubscriber/
```

Existing modules use folders such as:

```text
Module/
├── Command/
├── Config/
├── Controller/
├── Entity/
├── EventListener/
├── Form/
├── Repository/
├── Resources/
├── Security/
├── Service/
├── Subscriber/
└── translations/
```

New travel modules must follow the same architectural style.

---

# 2. Product vision

AlefBayeSafar is **not just a tour-selling website**.

The target is a **Travel Intelligence Platform / Travel Decision Engine** that becomes a reference point for users before they travel.

Instead of opening many different travel websites, users should be able to use one platform to:

- choose origin and destination;
- choose exact or flexible dates;
- specify number of adults and children;
- specify children's ages;
- define budget;
- choose flight class;
- specify hotel requirements;
- choose travel style and interests;
- search and compare hotels;
- inspect flights and airlines;
- discover activities, events and attractions;
- see our own tour packages;
- compare our tour package with independent travel options;
- read real customer reviews collected from configured external sources;
- see AI-generated Persian summaries of those real reviews;
- ask AI questions about hotels, airlines, activities and destinations;
- receive a personalized day-by-day travel plan;
- save a trip;
- later submit booking requests.

Long-term goal:

> A user planning a trip should first visit AlefBayeSafar instead of searching ten or twenty separate sites.

---

# 3. Existing Lovable MVP

There is an existing MVP built with Lovable:

`https://custom-trip-finder.lovable.app/build`

This MVP is a **reference implementation**, especially for UI/UX and the initial trip wizard.

Its current stack includes:

- TanStack Start
- React
- Tailwind CSS
- shadcn/ui
- RTL
- Persian UI
- Jalali calendar
- PostgreSQL
- Lovable Auth / RLS
- Firecrawl
- AI
- PWA

Important existing concepts:

- four-step travel wizard;
- origin and destination;
- exact/flexible date mode;
- adults and children;
- budget;
- flight class;
- hotel preferences;
- activities;
- additional services;
- Firecrawl web search;
- AI normalization;
- saved packages;
- admin offers;
- search cache.

The **visual language and UX of the Lovable public frontend should be preserved as closely as practical** when rebuilding it in Symfony.

Do not copy its backend architecture blindly.

---

# 4. Symfony frontend decision

Initial Symfony frontend approach:

- Twig
- Stimulus
- Tailwind
- Symfony UX

Do not introduce a separate React SPA in the first phases unless there is a proven technical need and explicit approval.

Initial public routes should include concepts such as:

```text
/
/build
/login
/register
/profile
/trips
/trips/{id}
```

The Lovable four-step wizard should remain conceptually similar:

```text
1. Trip
2. Flight / Hotel
3. Activities
4. Confirmation / Results
```

---

# 5. Core architecture principles

These principles are non-negotiable unless explicitly changed later.

## 5.1 Symfony owns the business

Symfony is the owner of:

- canonical data;
- business logic;
- users;
- catalog;
- tours;
- prices;
- offers;
- booking requests;
- provider configuration;
- synchronization state;
- AI execution metadata.

External systems are providers, not the application core.

## 5.2 External providers must be replaceable

Firecrawl, OpenAI, a scraper, an affiliate API or a supplier API must never become the domain model.

Architecture principle:

```text
External source
      ↓
Provider layer
      ↓
Raw/source data
      ↓
Normalization
      ↓
Canonical data
      ↓
Business logic
```

## 5.3 AI is not the source of truth

AI may:

- translate;
- summarize;
- classify;
- rank;
- explain;
- generate travel plans;
- analyze review sentiment;
- generate search queries.

AI must **not** fabricate factual travel inventory.

Especially:

```text
NO: AI invents a hotel price
NO: AI invents a flight price
NO: AI invents a review
NO: AI invents availability

YES: providers supply data
YES: AI explains and summarizes provider data
```

If no reliable price is available, return a state equivalent to:

`Price unavailable`

Do not invent a market estimate and present it as real data.

## 5.4 Catalog and commerce must be separate

Canonical entity data and commercial offers must not be mixed.

Examples:

- Hotel != HotelOffer
- Flight != FlightOffer
- Activity != ActivityOffer
- Tour catalog references must not own Hotel/Airline/Activity master data

## 5.5 Tour packages consume canonical entities

A tour package links to hotels, flights, activities and services.

If a hotel description changes later, connected tours should see the current canonical hotel information.

However, the commercial price agreed for a particular tour/departure must remain independent.

---

# 6. Planned modules

Long-term module map:

```text
src/Modules/
├── Auth/
├── Default/
├── User/
├── Destination/
├── Hotel/
├── Airline/
├── Flight/
├── Activity/
├── Tour/
├── SearchSource/
├── Review/
├── AI/
├── TripPlanner/
├── Booking/
└── Supplier/
```

Do **not** create all of these prematurely.

Each phase should add only the modules it genuinely needs.

---

# 7. Geographic catalog

Travel entities need a shared geographic backbone.

Core entities:

```text
Country
City
District
Airport
```

Example:

```text
Turkey
└── Istanbul
    ├── Taksim
    ├── Sultanahmet
    ├── Harbiye
    ├── IST
    └── SAW
```

Hotels, activities, tours, airports and later other catalog entities should use this structure.

---

# 8. Hotel domain

## 8.1 Hotel is canonical information only

Hotel must not contain a single generic selling price.

Typical canonical Hotel fields may include:

```text
id
name
nameFa
slug
city
district
stars
address
latitude
longitude
website
phone
checkIn
checkOut
descriptionOriginal
descriptionFa
active
verified
createdAt
updatedAt
```

Related concepts:

```text
HotelAmenity
HotelImage
HotelSource
HotelExternalId
HotelOffer
HotelReview
HotelAISummary
```

## 8.2 Hotel must support search/import

Admin should not be forced to manually type every hotel.

Preferred UX:

```text
Search hotel
    ↓
search configured sources
    ↓
show candidates
    ↓
Import
    ↓
normalize
    ↓
duplicate detection
    ↓
save canonical Hotel
    ↓
optionally translate/summarize Persian content
```

## 8.3 Inline import UX

When building a tour, the admin must not need to leave the Tour Builder just because a hotel does not yet exist.

Preferred workflow:

```text
Search
→ Select existing
→ or Search web
→ Import & Select
→ Continue
```

The same principle should later apply to activities, airlines, destinations and other reusable catalog objects.

## 8.4 Hotel source data must remain attributable

Source data must not blindly overwrite canonical data.

One hotel can have references from multiple sources:

```text
Booking ───────┐
Agoda ─────────┼──→ Canonical Hotel
Google ────────┤
Tripadvisor ───┘
```

Keep source identifiers, URLs and sync timestamps.

## 8.5 Duplicate detection

The same hotel may be found under different names.

Potential matching signals:

- normalized name;
- address;
- coordinates;
- phone;
- website;
- source external IDs.

Avoid creating duplicates.

---

# 9. Search source management

Search targets must be configurable from Admin.

Do not hardcode a permanent list of travel websites in domain services.

Core concept:

`SearchSource`

Suggested properties/capabilities:

```text
name
domain
enabled
country
language
priority
providerType
supportsHotel
supportsFlight
supportsAirline
supportsReview
supportsActivity
supportsTour
```

Provider types can include:

```text
FIRECRAWL
API
SCRAPER
AFFILIATE_API
MANUAL
```

An admin should be able to enable, disable and prioritize sources.

Different sources may support different capabilities.

---

# 10. Provider abstraction

Domain services must not depend directly on Firecrawl.

Use a provider abstraction such as:

```text
TravelDataProviderInterface
```

Possible implementations over time:

```text
FirecrawlProvider
BookingProvider
TripadvisorProvider
GoogleProvider
GetYourGuideProvider
FlightProvider
SupplierProvider
```

Example architectural intent:

```text
HotelImportService
       ↓
TravelDataProviderInterface
       ↓
actual provider
```

A future migration from Firecrawl to an official API should not require rewriting the Hotel domain.

---

# 11. Data freshness and synchronization

Travel data changes frequently.

Not every data type should use the same TTL.

## 11.1 Relatively stable

Examples:

- country;
- city;
- airport identity;
- airline identity;
- hotel identity.

Refresh less frequently.

## 11.2 Semi-dynamic

Examples:

- hotel amenities;
- hotel descriptions;
- reviews;
- ratings;
- activity information;
- airline reviews.

Refresh periodically.

## 11.3 Live or near-live

Examples:

- hotel price;
- flight price;
- availability;
- activity availability.

Fetch on demand or with a short TTL.

Useful sync/source metadata:

```text
source
sourceExternalId
sourceUrl
firstSeenAt
lastSeenAt
lastSyncedAt
nextSyncAt
dataUpdatedAt
syncStatus
checksum
```

Admin should eventually show freshness such as:

```text
Fresh
Needs refresh
Stale
```

---

# 12. Messenger / background sync

Long-running synchronization should use Symfony Messenger.

Do not run heavy provider crawling/sync work synchronously in an interactive HTTP request unless intentionally designed for a small lookup.

Possible future messages:

```text
HotelSyncMessage
HotelReviewSyncMessage
AirlineSyncMessage
ActivitySyncMessage
ReviewSummaryMessage
```

General pattern:

```text
Request / Scheduler / Admin action
          ↓
       Message
          ↓
      Messenger
          ↓
       Handler
          ↓
      Provider
          ↓
      Database
```

---

# 13. Review architecture

Reviews displayed as customer feedback must originate from real sources.

AI must never fabricate user reviews.

Possible HotelReview fields:

```text
hotel
source
externalId
author
country
rating
title
content
travelerType
reviewDate
language
sourceUrl
createdAt
```

Similar review concepts may later exist for:

- hotels;
- airlines;
- activities.

Source attribution should remain available.

---

# 14. AI review intelligence

AI may generate a cached analysis based on collected reviews.

Example concept:

```text
HotelAISummary
```

Possible fields:

```text
hotel
language
summary
pros
cons
locationScore
cleanlinessScore
serviceScore
breakfastScore
noiseScore
familyScore
coupleScore
businessScore
reviewCountUsed
generatedAt
sourceUpdatedUntil
aiModel
```

Do not regenerate expensive summaries on every page request.

Regenerate when underlying review data has materially changed or according to an explicit freshness policy.

---

# 15. Hotel Q&A

Users should eventually be able to ask questions such as:

> Is this hotel suitable for a four-year-old child?

The answer should use grounded data:

```text
Hotel data
+ Amenities
+ Location
+ Real reviews
+ AI review summary
```

The assistant should clearly distinguish known data from uncertain/incomplete data.

---

# 16. Airline domain

Airline is a canonical catalog entity.

Possible fields:

```text
name
nameFa
iataCode
icaoCode
country
logo
website
description
```

Related concepts:

```text
AirlineSource
AirlineReview
AirlineRatingSnapshot
AirlineAISummary
```

Airline and Flight must remain separate concepts.

---

# 17. Flight domain

Conceptual relationship:

```text
Airline
   ↓
Flight
   ↓
FlightOffer
```

A Flight contains identity/schedule data such as:

```text
flightNumber
airline
departureAirport
arrivalAirport
departureTime
arrivalTime
aircraft
```

A FlightOffer contains commercial data such as:

```text
provider
flight
cabin
baggage
price
currency
availability
bookingUrl
expiresAt
```

A flight must not have one permanent universal price.

---

# 18. Activity / Event / Attraction domain

Activities and events must follow the same reusable-catalog pattern.

Potential entities:

```text
Activity
ActivityCategory
ActivitySession
ActivityOffer
ActivitySource
ActivityReview
ActivityAISummary
```

Examples:

- Bosphorus Dinner Cruise
- Istanbul Aquarium
- Museum of the Future
- Disneyland Paris

Activity = canonical information.

ActivitySession = date/time/capacity.

ActivityOffer = commercial price/availability/provider.

---

# 19. Tour package domain

Tour packages are commercial compositions of reusable catalog components.

Conceptually:

```text
TourPackage
├── Flight
├── Hotel
├── Activity
├── Activity
├── Transfer
└── Insurance
```

## 19.1 Tour departures

A tour may have many departures:

```text
TourPackage
    ↓
TourDeparture
```

Example:

```text
21 Sep → 25 Sep
28 Sep → 2 Oct
5 Oct → 9 Oct
```

## 19.2 Tour prices are independent

Tour selling price must not be derived automatically from current market component prices unless a specific business rule explicitly requests it.

Example:

```text
market component total: €720
our package price:      €649
```

Both can coexist.

Possible pricing concepts:

```text
adultPrice
childPrice
infantPrice
singleSupplement
currency
```

## 19.3 Multiple hotel options per tour

One tour can offer different hotel choices.

Example:

```text
Istanbul 5 Days

Arts Hotel
Adult €799

CVK Park
Adult €950

Elite World
Adult €870
```

A relation such as `TourPackageHotel` or an equivalent domain design should support:

```text
tourPackage
hotel
roomType
mealPlan
adultPrice
childPrice
infantPrice
singleSupplement
currency
availableRooms
active
```

---

# 20. Direct hotel offers

Hotel booking offers are separate from the Hotel entity and separate from tour pricing.

Example:

```text
Arts Hotel

Booking    €119
Agoda      €111
Our Price   €95
```

Concept:

`HotelOffer`

Possible providers include:

```text
OWN
SUPPLIER
API_PROVIDER
AFFILIATE_PROVIDER
```

---

# 21. Tour Builder admin UX

Admin Tour Builder must be designed for fast workflow.

Example:

```text
Create Tour

Destination
Departure
Return

Flight
[ Search / Select ]

Hotel
[ Search / Select ]

Activities
[ Search / Add ]

Prices
Adult
Child
Infant
```

Missing reusable objects should support inline search/import instead of forcing navigation away from the builder.

---

# 22. Trip planner

The existing Lovable wizard is the starting point, not the final planner.

Input concepts include:

```text
origin
destination
dateMode
departDate
returnDate
windowStart
windowEnd
tripDays
adults
children
childrenAges
budget
flightClass
stops
hotelStars
roomType
breakfast
activities
visa
insurance
transfer
simcard
```

Future preference concepts:

```text
travelStyle
seniorTraveler
mobilityNeeds
preferredPace
interests
```

Potential travel styles:

```text
Family
Couple
Solo
Luxury
Budget
Adventure
Relax
Shopping
Food
History
Nightlife
```

---

# 23. Search / recommendation pipeline

Target architecture:

```text
User request
     ↓
Query Builder
     ↓
Configured Search Sources
     ↓
┌───────────┬───────────┬────────────┐
API      Firecrawl    Scraper      Supplier
└───────────┴───────────┴────────────┘
     ↓
Raw provider data
     ↓
Normalization
     ↓
Canonical entities / offers
     ↓
Ranking
     ↓
AI explanation
     ↓
User
```

Our own tours should be eligible for a business-priority boost, but market alternatives should not be hidden.

Example presentation:

```text
Our Package
€1490

Independent Trip
€1570–€1740
```

---

# 24. OpenAI usage

OpenAI may eventually be used for:

- travel planning;
- hotel review summaries;
- airline review summaries;
- activity review summaries;
- hotel Q&A;
- destination advisor;
- tour recommendation;
- search query generation;
- result ranking;
- Persian translation;
- content summarization;
- intent detection.

OpenAI must be accessed through an application service/abstraction and must not be tightly coupled into entities.

---

# 25. Public content and SEO vision

Destination pages should eventually become major product surfaces.

Example:

```text
/istanbul
```

Possible content:

- destination guide;
- best time to visit;
- estimated costs;
- hotels;
- flights;
- our tours;
- activities;
- attractions;
- review intelligence;
- sample itineraries;
- Ask AI.

Hotel page example:

```text
/hotels/arts-hotel-istanbul
```

Possible content:

- name;
- stars;
- photos;
- map;
- description;
- amenities;
- AI review summary;
- pros;
- cons;
- family suitability;
- real reviews;
- price offers;
- our price;
- tours using this hotel;
- Hotel Q&A.

Future SEO routes may include:

```text
/istanbul/hotels
/istanbul/family-hotels
/istanbul/5-star-hotels
/istanbul/tours
/istanbul/with-kids
/istanbul/5-days
/istanbul/from-cologne
/istanbul/from-tehran
```

SEO work should later include:

- sitemap;
- canonical URLs;
- OpenGraph;
- structured data / Schema.org;
- destination/tour/hotel content architecture.

---

# 26. Booking strategy

Do not build a full reservation engine in the first MVP.

Initial flow:

```text
Request Booking
      ↓
Lead
      ↓
Admin follow-up
```

Later phases may introduce:

```text
Reservation
Payment
Invoice
Voucher
```

---

# 27. Admin menu vision

Long-term admin information architecture:

```text
Dashboard

Catalog
 ├── Countries
 ├── Cities
 ├── Airports
 ├── Hotels
 ├── Airlines
 ├── Activities
 └── Attractions

Tours
 ├── Packages
 ├── Departures
 ├── Prices
 └── Suppliers

Offers
 ├── Hotel Offers
 ├── Flight Offers
 └── Activity Offers

Travel
 ├── Trip Searches
 ├── Saved Trips
 ├── AI Plans
 └── Booking Requests

Reviews
 ├── Hotel Reviews
 ├── Airline Reviews
 └── Activity Reviews

Data Sources
 ├── Sources
 ├── Sync Jobs
 ├── Import Logs
 └── Errors

AI
 ├── Prompts
 ├── Summaries
 ├── Usage
 └── Logs

Content
 ├── Destination Pages
 ├── Guides
 └── SEO

Customers
 ├── Users
 └── Leads

System
 ├── Roles
 ├── Permissions
 ├── Menu
 ├── Currencies
 └── Settings
```

Do not rebuild existing template features such as roles, permissions, dynamic menus or settings.

---

# 28. Cache strategy

Caching is required to control provider and AI costs.

Do not use one universal TTL for everything.

Potential cache layers:

- search query cache;
- provider response cache;
- AI summary cache;
- hotel metadata freshness;
- review freshness;
- live offer TTL.

Cache policy must follow data freshness requirements.

---

# 29. Database decision

The base Symfony template uses MySQL/MariaDB.

Use the template's Doctrine/MySQL/MariaDB approach unless a later requirement creates a strong reason to move to PostgreSQL.

The Lovable PostgreSQL implementation is not a requirement.

---

# 30. Persian content

Where useful, retain original source content and separately store Persian content.

Example:

```text
descriptionOriginal
descriptionFa
```

AI may assist with translation/summarization.

Always retain enough source metadata to understand where imported data came from.

---

# 31. Development phases

Development must be incremental.

## Phase 0 — Project Foundation

- derive AlefBayeSafar from SymfonyAppTemplate;
- project-specific configuration;
- new database;
- validate existing auth/admin foundation;
- establish project documentation and references;
- ensure test/quality baseline passes.

## Phase 1 — Lovable UI/UX Migration

- public site layout;
- RTL;
- Tailwind;
- four-step wizard UI;
- responsive behavior;
- Jalali date picker behavior;
- results UI shell;
- login/register integration;
- saved trips placeholder/interface;
- reuse existing Symfony auth/admin systems.

Important:

Do not implement Hotel/Tour/Firecrawl/OpenAI/Review/Sync/Flight/Activity business logic in this phase.

## Phase 2 — Destination Catalog

- Country
- City
- District
- Airport
- admin CRUD

## Phase 3 — Hotel Catalog

- Hotel
- HotelAmenity
- HotelImage
- HotelSource / external references
- admin list/detail

## Phase 4 — Search Sources / Provider Layer

- SearchSource
- capabilities/config
- provider abstractions
- first Firecrawl integration

## Phase 5 — Hotel Search & Import

- search;
- provider results;
- import;
- normalization;
- duplicate detection;
- Persian content;
- source attribution;
- inline import/select.

## Phase 6 — Sync Engine

- Messenger messages/handlers;
- freshness;
- retry/error handling;
- sync logs;
- scheduling policy.

## Phase 7 — Tour Management

- TourPackage;
- TourDeparture;
- TourPrice;
- TourPackageHotel;
- Supplier;
- Tour Builder.

## Phase 8 — Activity

- Activity;
- ActivityCategory;
- ActivitySession;
- ActivityOffer;
- ActivitySource;
- tour integration.

## Phase 9 — Airline / Flight

- Airline;
- Flight;
- FlightOffer;
- tour integration.

## Phase 10 — Reviews

- HotelReview;
- AirlineReview;
- ActivityReview;
- review imports/sync.

## Phase 11 — AI Intelligence

- cached summaries;
- review analysis;
- Q&A;
- AI service abstraction.

## Phase 12 — Trip Planner

- preferences;
- provider search;
- ranking;
- personalized recommendations;
- itinerary;
- saved trip.

## Phase 13 — Destination / SEO Portal

- destination pages;
- hotel pages;
- activity pages;
- tour pages;
- structured data;
- sitemap;
- SEO landing pages.

## Phase 14 — Booking Requests / Leads

- booking request;
- lead;
- admin workflow.

---

# 32. Initial MVP target

The first meaningful MVP should ultimately support:

```text
✓ Lovable-inspired UI/UX on Symfony
✓ User/Auth
✓ Admin
✓ Country / City
✓ Hotel
✓ Search Source
✓ Hotel Search & Import
✓ Inline Import
✓ Tour Package
✓ Tour Departure
✓ Hotel selection for Tour
✓ Adult / Child / Infant pricing
✓ Activity
✓ Activity inside Tour
✓ Airline
✓ Flight/Airline selection
✓ Travel Wizard
✓ Own Tour display
✓ Firecrawl-powered search
✓ OpenAI-powered recommendation/explanation
✓ Saved Trip
```

Explicitly not part of the early MVP:

```text
✗ Full payment system
✗ Full reservation engine
✗ Native mobile app
✗ Hundreds of providers
✗ Massive review crawling
✗ Complex affiliate network
```

---

# 33. Branch / PR strategy

Use small, focused branches/PRs.

Example:

```text
feature/travel-foundation
feature/destination-catalog
feature/hotel-catalog
feature/search-sources
feature/hotel-import
feature/sync-engine
feature/reviews
feature/ai-summary
feature/airlines
feature/flights
feature/activities
feature/tours
feature/trip-planner
```

Do not implement the entire roadmap in one branch.

---

# 34. Current execution target

When this file is first introduced, the immediate implementation target is:

```text
Phase 0 — Project Foundation
Phase 1 — Lovable UI/UX Migration
```

Before writing code:

1. audit the current repository;
2. understand what SymfonyAppTemplate already provides;
3. identify what can be reused;
4. identify the minimum files/modules required;
5. define acceptance criteria;
6. do not prematurely build future domain models.

Phase 1 must leave the architecture ready for future modules without implementing them early.

