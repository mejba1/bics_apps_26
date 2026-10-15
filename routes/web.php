<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BackendController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegisterNotificationController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\RaffleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PageBuilderController;
use App\Http\Controllers\FormOptionController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;


Route::get('/', [FrontendController::class, 'home'])->name('home');


Route::get('/global-business-conference-vietnam-2025', [FrontendController::class, 'vietnameConference'])->name('vietnameConference');

Route::get('/bics-overview', [FrontendController::class, 'bicsOverview'])->name('bicsOverview');
Route::get('/upcomming-bics', [FrontendController::class, 'upcommingBics'])->name('upcommingBics');
Route::get('/library-research', [FrontendController::class, 'libraryResearch'])->name('libraryResearch');
Route::get('/contact-us', [FrontendController::class, 'contact'])->name('contact');
Route::get('/gallery', [FrontendController::class, 'gallery'])->name('gallery');
Route::get('/media', [FrontendController::class, 'media'])->name('media');
Route::get('/registration', [FrontendController::class, 'registration'])->name('registration');
//Registration for people who joined an earlier event: one-time code by email, then the form is filled in
Route::post('/registration/returning/send', [\App\Http\Controllers\ReturningRegistrationController::class, 'send'])->middleware(['throttle:6,1,reg-returning-send', 'form.guard:returning'])->name('registration.returning.send');
Route::post('/registration/returning/verify', [\App\Http\Controllers\ReturningRegistrationController::class, 'verify'])->middleware('throttle:12,1,reg-returning-verify')->name('registration.returning.verify');
Route::post('/registration/returning/submit', [\App\Http\Controllers\ReturningRegistrationController::class, 'submit'])->middleware(['throttle:10,1,reg-returning-submit', 'form.guard:registration'])->name('registration.returning.submit');
Route::post('/registration/returning/reset', [\App\Http\Controllers\ReturningRegistrationController::class, 'reset'])->middleware('throttle:30,1,reg-returning-reset')->name('registration.returning.reset');
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])->name('contact.send')->middleware(['throttle:5,1,contact', 'form.guard:contact']);
//Verification Success Message
Route::get('/success', [FrontendController::class, 'success'])->name('success');

//Registration Form Submit
Route::post('/saveRegisterInfo', [\App\Http\Controllers\RegistrationController::class, 'store'])->name('store')->middleware(['throttle:10,1', 'form.guard:registration']);
Route::post('speciality', [RegistrationController::class, 'speciality'])->middleware('throttle:60,1,speciality');

//Participant digital profile (NFC card / QR code / link): permanent URL /p/{code}
Route::get('/p/{code}', [\App\Http\Controllers\ProfileController::class, 'show'])->where('code', '[A-Z]{2,10}-\d{6}')->middleware('throttle:120,1,profile')->name('profile.show');
Route::get('/p/{slug}', [\App\Http\Controllers\ProfileController::class, 'slug'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:120,1,profile')->name('profile.slug');
Route::get('/p/{code}/vcard', [\App\Http\Controllers\ProfileController::class, 'vcard'])->where('code', '[A-Z]{2,10}-\d{6}')->middleware('throttle:60,1,profile-vcard')->name('profile.vcard');
Route::get('/p/{code}/qr.{format}', [\App\Http\Controllers\ProfileController::class, 'qr'])->where(['code' => '[A-Z]{2,10}-\d{6}', 'format' => 'svg|png|eps'])->middleware('throttle:120,1,profile-qr')->name('profile.qr');
Route::post('/p/{code}/track', [\App\Http\Controllers\ProfileController::class, 'track'])->where('code', '[A-Z]{2,10}-\d{6}')->middleware('throttle:60,1,profile-track')->name('profile.track');

//Participant dashboard (/my): sign in with an emailed link or code, own profile, QR code, card, visits
Route::prefix('my')->name('my.')->group(function(){
    $c = \App\Http\Controllers\MyController::class;
    Route::get('/login', [$c, 'login'])->name('login');
    Route::post('/login', [$c, 'send'])->middleware(['throttle:6,1,my-send', 'form.guard:my-login'])->name('send');
    Route::get('/login/{token}', [$c, 'link'])->where('token', '[A-Za-z0-9]{48}')->name('login.link');
    Route::post('/login/link', [$c, 'useLink'])->middleware('throttle:20,1,my-link')->name('login.use');
    Route::post('/login/code', [$c, 'useCode'])->middleware(['throttle:10,1,my-code', 'form.guard:my-login'])->name('login.code');
    Route::post('/login/password', [$c, 'usePassword'])->middleware(['throttle:8,1,my-password', 'form.guard:my-login'])->name('login.password');
    Route::middleware(['auth:participant', 'participant.active'])->group(function() use ($c){
        Route::get('/', [$c, 'home'])->name('home');
        Route::get('/events', [$c, 'events'])->name('events');
        Route::get('/events/{registration}/id-card', [$c, 'idCard'])->whereNumber('registration')->name('idcard');
        Route::get('/card', [$c, 'card'])->name('card');
        Route::get('/birthday-card.jpg', [$c, 'birthdayCard'])->middleware('throttle:20,1,my-bday')->name('birthday-card');
        Route::get('/security', [$c, 'security'])->name('security');
        Route::post('/security/password', [$c, 'password'])->middleware('throttle:10,1,my-pass-set')->name('password');
        Route::post('/security/password/remove', [$c, 'removePassword'])->middleware('throttle:10,1,my-pass-set')->name('password.remove');
        Route::post('/security/sign-out-others', [$c, 'signOutOthers'])->name('signout-others');
        Route::get('/profile', [$c, 'edit'])->name('edit');
        Route::post('/profile', [$c, 'update'])->middleware('throttle:30,1,my-save')->name('update');
        Route::post('/upload', [$c, 'upload'])->middleware('throttle:20,1,my-upload')->name('upload');
        Route::post('/logout', [$c, 'logout'])->name('logout');
    });
});

//NFC card tap: /n/{token} → the profile of the card's participant (only while the card is active)
Route::get('/n/{token}', [\App\Http\Controllers\NfcTapController::class, 'tap'])->where('token', '[A-Za-z0-9]{10}')->middleware('throttle:120,1,nfc-tap')->name('nfc.tap');

//Certificate of participation: public page (email link / QR code) and PDF
Route::get('/certificate/{token}', [\App\Http\Controllers\CertificatePublicController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('certificate.show');
Route::get('/certificate/{token}/pdf', [\App\Http\Controllers\CertificatePublicController::class, 'pdf'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1,cert-pdf')->name('certificate.pdf');

//Self check-in at the venue: poster QR → registration number → welcome (Admin › Self Check-in)
Route::get('/checkin/{slug}/{token}', [\App\Http\Controllers\CheckInController::class, 'show'])->where(['slug' => '[a-z0-9-]+', 'token' => '[A-Za-z0-9]{6,32}'])->middleware('throttle:120,1,checkin-page')->name('checkin.page');
Route::post('/checkin/{slug}/{token}', [\App\Http\Controllers\CheckInController::class, 'submit'])->where(['slug' => '[a-z0-9-]+', 'token' => '[A-Za-z0-9]{6,32}'])->middleware('throttle:20,1,checkin-submit')->name('checkin.submit');
Route::post('/checkin/{slug}/{token}/find', [\App\Http\Controllers\CheckInController::class, 'find'])->where(['slug' => '[a-z0-9-]+', 'token' => '[A-Za-z0-9]{6,32}'])->middleware('throttle:5,10,checkin-find')->name('checkin.find');

//Fetch Register Data By QR Code
Route::get('/verify/{qrcode}', [\App\Http\Controllers\RegistrationController::class, 'verify'])->where('qrcode', '[0-9]{1,12}')->middleware('throttle:30,1,verify-badge');

Route::get('/user-profile', [FrontendController::class,'userprofile'])->middleware('throttle:30,1,user-profile');




//Ajax form Submit
Route::get('/ajax-registration', [FrontendController::class, 'ajaxregistration'])->name('ajaxregistration');

//Verify Identity by input by Valid Pin No
// Check-in desk: staff only (signed in, permission "attendees.verify") — it marks people as checked in
Route::group(['middleware' => ['auth', 'can:attendees.verify']], function(){
    Route::get('/verify', [VerifyController::class, 'verifyinfo'])->name('verifyinfo');
    Route::get('/verifyData', [VerifyController::class, "verifyData"])->middleware('throttle:120,1,verify-data')->name('verifydata');
});


//Authentication
Route::group(['middleware'=>['guest', 'disable_back_btn']],function(){
    Route::get('login', [CustomAuthController::class, 'login'])->name('login');
    Route::post('custom-login', [CustomAuthController::class, 'customeLogin'])->middleware(['throttle:20,1,admin-login', 'form.guard:admin-login'])->name('login.custom');    
});

Route::group(['middleware'=>'disable_back_btn'], function(){
    Route::group(['middleware'=>'auth'], function(){

        // Every admin page needs a permission (Admin > Roles & Permissions, see App\Support\Permissions)

        //Dashboard (people without "registrations.view" get a page with the links they can use)
        Route::get('/home', [CustomAuthController::class, 'index']);

        // Pin an event: every admin page opens on it (personal choice, so any signed-in admin)
        Route::post('/pin-event', [\App\Http\Controllers\ReportController::class, 'pin'])->name('events.pin');
        // card downloads (PNG / JPG / EPS made in the browser from this data; PDF on the server) — cards.design or cards.print
        Route::post('/card-export/data', [\App\Http\Controllers\CardExportController::class, 'data'])->middleware('throttle:60,1,card-export')->name('card-export.data');
        Route::post('/card-export/pdf', [\App\Http\Controllers\CardExportController::class, 'pdf'])->middleware('throttle:30,1,card-export-pdf')->name('card-export.pdf');
        // Verify Attendee / Gift Distribution: many at once (permission checked inside: attendees.verify / attendees.gifts)
        Route::post('/attendees/{mode}/bulk', [BackendController::class, 'attendeeBulk'])->where('mode', 'verify|gift')->middleware('throttle:60,1,attendee-bulk')->name('attendees.bulk');
        // the bell: "View all" and "mark as read"
        Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read', [\App\Http\Controllers\NotificationController::class, 'read'])->name('notifications.read');
        // Ended / archived events are read-only; a Super Admin can open one for corrections
        Route::post('/events/{event}/unlock', [\App\Http\Controllers\EventController::class, 'unlock'])->whereNumber('event')->name('events.unlock');
        Route::post('/events/{event}/lock', [\App\Http\Controllers\EventController::class, 'lock'])->whereNumber('event')->name('events.lock');

        //Registrations: lists, reports, delete, resend emails
        Route::group(['middleware'=>'can:registrations.view'], function(){
            Route::get('/venue/details/{venue}', [BackendController::class, 'venue'])->name('venue.dtails');
        });
        Route::group(['middleware'=>'can:registrations.export'], function(){
            Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
            Route::get('/registrations/{registration}/id-card', [\App\Http\Controllers\ReportController::class, 'idCard'])->name('registrations.id-card');
            Route::get('/reports/download', [\App\Http\Controllers\ReportController::class, 'download'])->name('reports.download');
            Route::get('/reports/preview', [\App\Http\Controllers\ReportController::class, 'preview'])->name('reports.preview');
            Route::get('/createPdf', [AdminController::class, 'createPdf'])->name('createPdf');
            Route::get('/country-wise-report/{country}', [AdminController::class, 'countryWiseReport'])->name('countryWiseReport');
            Route::get('/download-profile-Pdf/{id}', [AdminController::class, 'profilePdf'])->name('profilePdf');
            Route::get('/refelDrawPdf', [AdminController::class, 'refelDrawPdf'])->name('refelDrawPdf');
            Route::get('/createPdfByVenue/{venue}', [BackendController::class, 'createPdfByVenue'])->name('createPdfByVenue');
            Route::get('/country-wise-report/{country}/{venue}', [BackendController::class, 'countryVenueWiseReport'])->name('countryVenueWiseReport');
        });
        Route::delete('/delete_register/{id}', [AdminController::class, 'destroy'])->middleware('can:registrations.delete');
        Route::group(['middleware'=>'can:registrations.create'], function(){
            Route::get('/registrations/new', [\App\Http\Controllers\AdminRegistrationController::class, 'create'])->name('registrations.create');
            Route::post('/registrations', [\App\Http\Controllers\AdminRegistrationController::class, 'store'])->name('registrations.store');
        });
        Route::get('/email-notify', [RegisterNotificationController::class, 'emailnotify'])->middleware('can:registrations.resend');
        Route::group(['middleware'=>'can:registrations.resend'], function(){
            // Send / re-send the confirmation email from the admin panel
            Route::post('/registrations/send-pending', [\App\Http\Controllers\AttendeeEmailController::class, 'sendPending'])->name('registrations.send-pending');
            Route::post('/registrations/{registration}/send-email', [\App\Http\Controllers\AttendeeEmailController::class, 'send'])->name('registrations.send-email');
        });

        //Attendee activities
        Route::get('/verify-user', [BackendController::class, 'verifyUser'])->name('verifyUser')->middleware('can:attendees.verify');
        Route::group(['middleware'=>'can:attendees.gifts'], function(){
            Route::get('/gift-distribute', [BackendController::class, 'giftDistribute'])->name('giftDistribute');
            Route::get('/gift/{id}', [BackendController::class, 'giftUpdate'])->name('gift.status');
            Route::post('/gift-distribute/{id}/give', [BackendController::class, 'giftGive'])->whereNumber('id')->name('gift.give');
            Route::post('/gift-distribute/{id}/undo', [BackendController::class, 'giftUndo'])->whereNumber('id')->name('gift.undo');
        });

        //Raffle draw: setup (prizes, who can win, hosts) and the draw screen (assigned hosts only, checked in the controller)
        Route::group(['middleware'=>'can:raffle.prizes'], function(){
            Route::get('/add-prize', [\App\Http\Controllers\RaffleSetupController::class, 'index'])->name('addPrize');
            Route::post('/raffle-setup/prizes', [\App\Http\Controllers\RaffleSetupController::class, 'storePrize'])->name('raffle.prizes.store');
            Route::post('/raffle-setup/prizes/order', [\App\Http\Controllers\RaffleSetupController::class, 'order'])->name('raffle.prizes.order');
            Route::get('/raffle-setup/entries', [\App\Http\Controllers\RaffleSetupController::class, 'entries'])->name('raffle.entries');
            Route::post('/raffle-setup/entries', [\App\Http\Controllers\RaffleSetupController::class, 'addEntries'])->name('raffle.entries.add');
            Route::post('/raffle-setup/entries/import', [\App\Http\Controllers\RaffleSetupController::class, 'importEntries'])->middleware('throttle:20,1,raffle-import')->name('raffle.entries.import');
            Route::post('/raffle-setup/entries/clear', [\App\Http\Controllers\RaffleSetupController::class, 'clearEntries'])->name('raffle.entries.clear');
            Route::get('/raffle-setup/entries/template', [\App\Http\Controllers\RaffleSetupController::class, 'entriesTemplate'])->name('raffle.entries.template');
            Route::delete('/raffle-setup/entries/{entry}', [\App\Http\Controllers\RaffleSetupController::class, 'deleteEntry'])->whereNumber('entry')->name('raffle.entries.delete');
            Route::post('/raffle-setup/email', [\App\Http\Controllers\RaffleSetupController::class, 'email'])->name('raffle.email');
            Route::post('/raffle-setup/email/preview', [\App\Http\Controllers\RaffleSetupController::class, 'emailPreview'])->name('raffle.email.preview');
            Route::post('/raffle-setup/email/test', [\App\Http\Controllers\RaffleSetupController::class, 'emailTest'])->middleware('throttle:6,1,raffle-mailtest')->name('raffle.email.test');
            Route::post('/raffle-setup/prizes/{prize}/email', [\App\Http\Controllers\RaffleSetupController::class, 'emailWinner'])->whereNumber('prize')->middleware('throttle:20,1,raffle-mail')->name('raffle.prizes.email');
            Route::post('/raffle-setup/sounds', [\App\Http\Controllers\RaffleSetupController::class, 'uploadSound'])->middleware('throttle:20,1,raffle-sound')->name('raffle.sounds.upload');
            Route::delete('/raffle-setup/sounds/{sound}', [\App\Http\Controllers\RaffleSetupController::class, 'deleteSound'])->where('sound', '[a-z0-9]{10}')->name('raffle.sounds.delete');
            Route::post('/raffle-setup/screen', [\App\Http\Controllers\RaffleSetupController::class, 'screen'])->name('raffle.screen');
            Route::post('/raffle-setup/order-mode', [\App\Http\Controllers\RaffleSetupController::class, 'orderMode'])->name('raffle.order-mode');
            Route::put('/raffle-setup/prizes/{prize}', [\App\Http\Controllers\RaffleSetupController::class, 'updatePrize'])->whereNumber('prize')->name('raffle.prizes.update');
            Route::delete('/raffle-setup/prizes/{prize}', [\App\Http\Controllers\RaffleSetupController::class, 'destroyPrize'])->whereNumber('prize')->name('raffle.prizes.destroy');
            Route::post('/raffle-setup/prizes/{prize}/undo', [\App\Http\Controllers\RaffleSetupController::class, 'undoWinner'])->whereNumber('prize')->name('raffle.prizes.undo');
            Route::post('/raffle-setup/hosts', [\App\Http\Controllers\RaffleSetupController::class, 'hosts'])->name('raffle.hosts');
            Route::post('/raffle-setup/eligibility', [\App\Http\Controllers\RaffleSetupController::class, 'eligibility'])->name('raffle.eligibility');
            Route::post('/raffle-setup/reset-skipped', [\App\Http\Controllers\RaffleSetupController::class, 'resetSkipped'])->name('raffle.reset-skipped');
            Route::get('/prize-winner', [\App\Http\Controllers\PrizeWinnerController::class, 'index'])->name('prize.winner');
            Route::get('/prize-winner/data', [\App\Http\Controllers\PrizeWinnerController::class, 'data'])->name('prize.winner.data');
            Route::get('/prize-winner/export', [\App\Http\Controllers\PrizeWinnerController::class, 'export'])->name('prize.winner.export');
            Route::post('/prize-winner/email-mode', [\App\Http\Controllers\PrizeWinnerController::class, 'emailMode'])->name('prize.winner.email-mode');
            Route::post('/prize-winner/email-pending', [\App\Http\Controllers\PrizeWinnerController::class, 'emailPending'])->middleware('throttle:6,1,winner-mail-all')->name('prize.winner.email-pending');
            Route::post('/prize-winner/{winner}/email', [\App\Http\Controllers\PrizeWinnerController::class, 'emailOne'])->whereNumber('winner')->middleware('throttle:30,1,winner-mail')->name('prize.winner.email');
        });
        Route::get('/raffle', [RaffleController::class, 'stage'])->name('raffle.stage');
        Route::get('/raffle/state', [RaffleController::class, 'state'])->name('raffle.state');
        Route::post('/raffle/draw', [RaffleController::class, 'draw'])->middleware('throttle:30,1,raffle-draw')->name('raffle.draw');
        Route::post('/raffle/accept', [RaffleController::class, 'accept'])->name('raffle.accept');
        Route::post('/raffle/redraw', [RaffleController::class, 'redraw'])->name('raffle.redraw');
        Route::redirect('/lottery', '/raffle'); // old screen

        //Participant profiles (permanent ID, public profile /p/{code}, QR code)
        Route::prefix('participants')->name('participants.')->middleware('can:participants.manage')->group(function(){
            Route::get('/', [\App\Http\Controllers\ParticipantController::class, 'index'])->name('index');
            // social icons: platforms added by admins (name, web addresses, icon / logo, colour)
            Route::get('/social-icons', [\App\Http\Controllers\SocialPlatformController::class, 'index'])->name('social-icons');
            Route::post('/social-icons', [\App\Http\Controllers\SocialPlatformController::class, 'save'])->middleware('throttle:30,1,social-icons')->name('social-icons.save');
            Route::delete('/social-icons/{type}', [\App\Http\Controllers\SocialPlatformController::class, 'destroy'])->where('type', 'c_[a-z0-9_]+')->name('social-icons.destroy');
            Route::get('/data', [\App\Http\Controllers\ParticipantController::class, 'data'])->name('data');
            Route::get('/export', [\App\Http\Controllers\ParticipantController::class, 'export'])->name('export');
            Route::get('/create', [\App\Http\Controllers\ParticipantController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\ParticipantController::class, 'store'])->name('store');
            Route::post('/upload', [\App\Http\Controllers\ParticipantController::class, 'upload'])->middleware('throttle:30,1,participant-upload')->name('upload');
            Route::post('/bulk', [\App\Http\Controllers\ParticipantController::class, 'bulk'])->name('bulk');
            Route::post('/link-settings', [\App\Http\Controllers\ParticipantController::class, 'linkSettings'])->name('link-settings');
            Route::post('/dashboard-settings', [\App\Http\Controllers\ParticipantController::class, 'dashboardSettings'])->name('dashboard-settings');
            Route::post('/{participant}/send-login', [\App\Http\Controllers\ParticipantController::class, 'sendLogin'])->whereNumber('participant')->middleware('throttle:10,1,participant-send-login')->name('send-login');
            Route::get('/{participant}/edit', [\App\Http\Controllers\ParticipantController::class, 'edit'])->whereNumber('participant')->name('edit');
            Route::put('/{participant}', [\App\Http\Controllers\ParticipantController::class, 'update'])->whereNumber('participant')->name('update');
        });

        //NFC cards: stock, assignment, encoding (Web NFC / external encoder), verification, activation, replacement
        Route::prefix('nfc-cards')->name('nfc.')->middleware('can:nfc.manage')->group(function(){
            Route::get('/', [\App\Http\Controllers\NfcCardController::class, 'index'])->name('index');
            Route::get('/data', [\App\Http\Controllers\NfcCardController::class, 'data'])->name('data');
            Route::get('/station', [\App\Http\Controllers\NfcCardController::class, 'station'])->name('station');
            Route::get('/participants', [\App\Http\Controllers\NfcCardController::class, 'participants'])->name('participants');
            Route::get('/export', [\App\Http\Controllers\NfcCardController::class, 'export'])->name('export');
            Route::post('/stock', [\App\Http\Controllers\NfcCardController::class, 'stock'])->name('stock');
            Route::post('/assign-event', [\App\Http\Controllers\NfcCardController::class, 'assignEvent'])->name('assign-event');
            Route::post('/bulk', [\App\Http\Controllers\NfcCardController::class, 'bulk'])->name('bulk');
            Route::post('/for/{participant}', [\App\Http\Controllers\NfcCardController::class, 'forParticipant'])->whereNumber('participant')->name('for');
            Route::get('/{card}', [\App\Http\Controllers\NfcCardController::class, 'show'])->whereNumber('card')->name('show');
            Route::post('/{card}/{action}', [\App\Http\Controllers\NfcCardController::class, 'action'])->whereNumber('card')->where('action', 'assign|unassign|encoded|encode_failed|verify|activate|deactivate|replace|printed|note')->name('action');
        });

        //Card Design Studio: card templates (front / back), editor, versions, previews
        Route::prefix('card-studio')->name('card-studio.')->middleware('can:cards.design')->group(function(){
            $c = \App\Http\Controllers\CardTemplateController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'store'])->name('store');
            Route::get('/preview', [$c, 'preview'])->name('preview');
            Route::get('/participants', [$c, 'participants'])->name('participants');
            Route::get('/data/{participant}', [$c, 'data'])->whereNumber('participant')->name('data');
            Route::post('/upload', [$c, 'upload'])->middleware('throttle:30,1,card-upload')->name('upload');
            Route::get('/{template}', [$c, 'edit'])->whereNumber('template')->name('edit');
            Route::put('/{template}', [$c, 'update'])->whereNumber('template')->name('update');
            Route::post('/{template}/settings', [$c, 'settings'])->whereNumber('template')->name('settings');
            Route::post('/{template}/duplicate', [$c, 'duplicate'])->whereNumber('template')->name('duplicate');
            Route::delete('/{template}', [$c, 'destroy'])->whereNumber('template')->name('destroy');
            Route::get('/{template}/versions/{version}', [$c, 'version'])->whereNumber('template')->whereNumber('version')->name('version');
        });

        //Participant analytics (profile visits, QR / NFC, top profiles, breakdowns)
        Route::middleware('can:reports.analytics')->group(function(){
            Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
            Route::get('/analytics/export/{kind}', [\App\Http\Controllers\AnalyticsController::class, 'export'])->where('kind', 'top|organizations|categories|countries|events|daily')->name('analytics.export');
        });

        //Organizations, categories & countries: spelling clean-up (rename / merge across participant profiles)
        Route::middleware('can:participants.manage')->group(function(){
            Route::get('/participants/values', [\App\Http\Controllers\ParticipantValuesController::class, 'index'])->name('participants.values');
            Route::get('/participants/values/data', [\App\Http\Controllers\ParticipantValuesController::class, 'data'])->name('participants.values.data');
            Route::post('/participants/values/rename', [\App\Http\Controllers\ParticipantValuesController::class, 'rename'])->name('participants.organizations');
        });

        //Audit log and platform settings
        Route::middleware('can:audit.view')->group(function(){
            Route::get('/system/audit', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
            Route::get('/system/audit/data', [\App\Http\Controllers\AuditController::class, 'data'])->name('audit.data');
            Route::get('/system/audit/export', [\App\Http\Controllers\AuditController::class, 'export'])->name('audit.export');
        });
        Route::middleware('can:system.settings')->group(function(){
            Route::get('/system/settings', [\App\Http\Controllers\PlatformSettingsController::class, 'index'])->name('system.settings.index');
            Route::post('/system/settings', [\App\Http\Controllers\PlatformSettingsController::class, 'update'])->name('system.settings');
            Route::post('/system/settings/prune', [\App\Http\Controllers\PlatformSettingsController::class, 'prune'])->name('system.settings.prune');
        });

        //Self check-in desk: live arrivals, help desk, posters; options need "event.settings"
        Route::middleware('can:attendees.verify')->prefix('checkin-desk')->name('checkin.')->group(function(){
            $c = \App\Http\Controllers\CheckInDeskController::class;
            Route::get('/', [$c, 'index'])->name('desk');
            Route::get('/feed', [$c, 'feed'])->name('feed');
            Route::get('/search', [$c, 'search'])->name('search');
            Route::get('/poster', [$c, 'poster'])->name('poster');
            Route::get('/qr.{format}', [$c, 'qr'])->where('format', 'svg|png|jpg|eps')->middleware('throttle:60,1,checkin-qr')->name('qr');
            Route::get('/export.xlsx', [$c, 'export'])->middleware('throttle:20,1,checkin-export')->name('export');
            Route::post('/check-in', [$c, 'checkIn'])->middleware('throttle:120,1,checkin-desk')->name('manual');
            Route::post('/undo', [$c, 'undo'])->name('undo');
            Route::post('/settings', [$c, 'settings'])->middleware('can:event.settings')->name('settings');
            Route::post('/new-qr', [$c, 'token'])->middleware('can:event.settings')->name('token');
        });

        //Birthday wishes (automatic daily cards, custom cards, designs)
        Route::middleware('can:birthdays.manage')->prefix('birthdays')->name('birthdays.')->group(function(){
            $c = \App\Http\Controllers\BirthdayController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/toggle', [$c, 'toggle'])->name('toggle');
            Route::post('/settings', [$c, 'settings'])->name('settings');
            Route::get('/search', [$c, 'search'])->name('search');
            Route::post('/people/{participant}/wishes', [$c, 'wishes'])->whereNumber('participant')->name('wishes');
            Route::get('/preview.jpg', [$c, 'preview'])->middleware('throttle:120,1,birthday-preview')->name('preview');
            Route::post('/preview-email', [$c, 'previewEmail'])->name('preview-email');
            Route::get('/cards/{card}/thumb.jpg', [$c, 'thumb'])->whereNumber('card')->name('thumb');
            Route::post('/cards', [$c, 'upload'])->middleware('throttle:20,1,birthday-upload')->name('cards.upload');
            Route::post('/cards/{card}', [$c, 'updateCard'])->whereNumber('card')->name('cards.update');
            Route::post('/cards/{card}/default', [$c, 'makeDefault'])->whereNumber('card')->name('cards.default');
            Route::delete('/cards/{card}', [$c, 'destroyCard'])->whereNumber('card')->name('cards.destroy');
            Route::post('/send', [$c, 'send'])->middleware('throttle:30,1,birthday-send')->name('send');
            Route::post('/run', [$c, 'run'])->middleware('throttle:6,1,birthday-run')->name('run');
            Route::post('/greetings/{greeting}/resend', [$c, 'resend'])->whereNumber('greeting')->middleware('throttle:30,1,birthday-send')->name('resend');
        });

        //ID cards on the participant dashboard: assign per event / venue
        Route::middleware('can:idcards.assign')->group(function(){
            Route::get('/id-cards', [\App\Http\Controllers\IdCardController::class, 'index'])->name('idcards.index');
            Route::get('/id-cards/data', [\App\Http\Controllers\IdCardController::class, 'data'])->name('idcards.data');
            Route::post('/id-cards/assign', [\App\Http\Controllers\IdCardController::class, 'assign'])->middleware('throttle:60,1,idcard-assign')->name('idcards.assign');
        });

        //Card printing: choose & check participants, print files (PDF / CSV / ZIP), print-job history
        Route::prefix('card-print')->name('card-print.')->middleware('can:cards.print')->group(function(){
            $c = \App\Http\Controllers\CardPrintController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('/search', [$c, 'search'])->name('search');
            Route::post('/check', [$c, 'check'])->middleware('throttle:30,1,card-print-check')->name('check');
            Route::post('/', [$c, 'store'])->middleware('throttle:10,1,card-print-make')->name('store');
            Route::get('/{job}/download/{kind}', [$c, 'download'])->whereNumber('job')->where('kind', 'pdf|csv|zip')->name('download');
            Route::get('/{job}/participants', [$c, 'participants'])->whereNumber('job')->name('participants');
            Route::post('/{job}/printed', [$c, 'printed'])->whereNumber('job')->name('printed');
            Route::delete('/{job}', [$c, 'destroy'])->whereNumber('job')->name('destroy');
        });

        //Certificates of participation (design, email, participant list, sending)
        Route::prefix('certificates')->name('certificates.')->middleware('can:certificates.manage')->group(function(){
            Route::get('/', [\App\Http\Controllers\CertificateController::class, 'index'])->name('index');
            Route::post('/design', [\App\Http\Controllers\CertificateController::class, 'saveDesign'])->name('design');
            Route::post('/design/reset', [\App\Http\Controllers\CertificateController::class, 'resetDesign'])->name('design.reset');
            Route::post('/preview', [\App\Http\Controllers\CertificateController::class, 'preview'])->middleware('throttle:60,1,cert-preview')->name('preview');
            Route::post('/upload', [\App\Http\Controllers\CertificateController::class, 'upload'])->middleware('throttle:30,1,cert-upload')->name('upload');
            Route::post('/email', [\App\Http\Controllers\CertificateController::class, 'saveEmail'])->name('email');
            Route::post('/email/test', [\App\Http\Controllers\CertificateController::class, 'testEmail'])->middleware('throttle:6,1,cert-test')->name('email.test');
            Route::get('/participants', [\App\Http\Controllers\CertificateController::class, 'participants'])->name('participants');
            Route::get('/{registration}/pdf', [\App\Http\Controllers\CertificateController::class, 'download'])->whereNumber('registration')->name('download');
            Route::post('/send', [\App\Http\Controllers\CertificateController::class, 'send'])->name('send');
            Route::post('/send-one', [\App\Http\Controllers\CertificateController::class, 'sendOne'])->middleware('throttle:30,1,cert-send-one')->name('send-one');
            Route::post('/assign', [\App\Http\Controllers\CertificateController::class, 'assign'])->middleware('throttle:60,1,cert-assign')->name('assign');
        });

        //Registration setup: event / registration dates, drop-down values (AJAX)
        Route::group(['middleware'=>'can:event.settings'], function(){
            // Events: create / edit / copy / publish / archive, and choose the current event
            Route::get('/events', [\App\Http\Controllers\EventController::class, 'index'])->name('events.index');
            Route::post('/events', [\App\Http\Controllers\EventController::class, 'store'])->name('events.store');
            Route::get('/events/{event}/edit', [\App\Http\Controllers\EventController::class, 'edit'])->name('events.edit');
            Route::put('/events/{event}', [\App\Http\Controllers\EventController::class, 'update'])->name('events.update');
            Route::post('/events/{event}/current', [\App\Http\Controllers\EventController::class, 'makeCurrent'])->name('events.current');
            Route::post('/events/{event}/status', [\App\Http\Controllers\EventController::class, 'status'])->name('events.status');
            Route::delete('/events/{event}', [\App\Http\Controllers\EventController::class, 'destroy'])->name('events.destroy');
            Route::get('/event-settings', [\App\Http\Controllers\EventController::class, 'editCurrent'])->name('event-settings.edit'); // old address: the current event
            Route::get('/event-settings/email-preview', [\App\Http\Controllers\EmailTemplateController::class, 'preview'])->name('event-settings.email-preview');

            // Confirmation email: texts, design, attachments
            Route::get('/email-template', [\App\Http\Controllers\EmailTemplateController::class, 'edit'])->name('email-template.edit');
            Route::put('/email-template', [\App\Http\Controllers\EmailTemplateController::class, 'update'])->name('email-template.update');
            Route::match(['get', 'post'], '/email-template/preview', [\App\Http\Controllers\EmailTemplateController::class, 'preview'])->name('email-template.preview');
            Route::post('/email-template/reset', [\App\Http\Controllers\EmailTemplateController::class, 'reset'])->name('email-template.reset');
            Route::post('/email-template/banner', [\App\Http\Controllers\EmailTemplateController::class, 'banner'])->name('email-template.banner');
            Route::post('/email-template/test', [\App\Http\Controllers\EmailTemplateController::class, 'test'])->middleware('throttle:10,1')->name('email-template.test');
            Route::post('/email-template/attachments', [\App\Http\Controllers\EmailAttachmentController::class, 'store'])->name('email-attachments.store');
            Route::post('/email-template/attachments/order', [\App\Http\Controllers\EmailAttachmentController::class, 'order'])->name('email-attachments.order');
            Route::post('/email-template/attachments/{attachment}', [\App\Http\Controllers\EmailAttachmentController::class, 'update'])->name('email-attachments.update');
            Route::delete('/email-template/attachments/{attachment}', [\App\Http\Controllers\EmailAttachmentController::class, 'destroy'])->name('email-attachments.destroy');
        });
        Route::group(['middleware'=>'can:form.options'], function(){
            Route::get('/form-options', [FormOptionController::class, 'index'])->name('form-options.index');
            Route::post('/form-options', [FormOptionController::class, 'store'])->name('form-options.store');
            Route::post('/form-options/reorder', [FormOptionController::class, 'reorder'])->name('form-options.reorder');
            Route::put('/form-options/{option}', [FormOptionController::class, 'update'])->name('form-options.update');
            Route::post('/form-options/{option}/toggle', [FormOptionController::class, 'toggle'])->name('form-options.toggle');
            Route::delete('/form-options/{option}', [FormOptionController::class, 'destroy'])->name('form-options.destroy');
        });

        //Website: menu, gallery, pages
        Route::group(['middleware'=>'can:website.menu'], function(){
            Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
            Route::post('/menu', [MenuController::class, 'store'])->name('menu.store');
            Route::put('/menu/{menu}', [MenuController::class, 'update'])->name('menu.update');
            Route::delete('/menu/{menu}', [MenuController::class, 'destroy'])->name('menu.destroy');
            Route::post('/menu/{menu}/toggle', [MenuController::class, 'toggle'])->name('menu.toggle');
            Route::post('/menu/{menu}/move/{direction}', [MenuController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('menu.move');
        });
        Route::prefix('gallery-manager')->name('gallery.')->middleware('can:website.gallery')->group(function(){
            Route::get('/', [GalleryController::class, 'index'])->name('index');
            Route::post('/albums', [GalleryController::class, 'storeAlbum'])->name('albums.store');
            Route::post('/albums/reorder', [GalleryController::class, 'reorderAlbums'])->name('albums.reorder');
            Route::put('/albums/{album}', [GalleryController::class, 'updateAlbum'])->name('albums.update');
            Route::post('/albums/{album}/toggle', [GalleryController::class, 'toggleAlbum'])->name('albums.toggle');
            Route::delete('/albums/{album}', [GalleryController::class, 'destroyAlbum'])->name('albums.destroy');
            Route::post('/albums/{album}/photos', [GalleryController::class, 'upload'])->name('photos.upload');
            Route::post('/albums/{album}/photos/reorder', [GalleryController::class, 'reorderPhotos'])->name('photos.reorder');
            Route::put('/photos/{photo}', [GalleryController::class, 'updatePhoto'])->name('photos.update');
            Route::delete('/photos/{photo}', [GalleryController::class, 'destroyPhoto'])->name('photos.destroy');
        });
        //Website › Documents (PDFs at their own short address)
        Route::prefix('documents')->name('documents.')->middleware('can:website.pages')->group(function(){
            $c = \App\Http\Controllers\DocumentController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::post('/', [$c, 'save'])->middleware('throttle:30,1,documents')->name('save');
            Route::post('/{id}/toggle', [$c, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('/{id}', [$c, 'destroy'])->whereNumber('id')->name('destroy');
            Route::get('/{id}/qr.{format}', [$c, 'qr'])->whereNumber('id')->where('format', 'png|svg')->name('qr');
        });

        Route::prefix('cms')->name('cms.')->middleware('can:website.pages')->group(function(){
            Route::get('/pages', [PageBuilderController::class, 'index'])->name('index');
            Route::post('/pages', [PageBuilderController::class, 'store'])->name('store');
            Route::get('/pages/{page}', [PageBuilderController::class, 'edit'])->name('edit');
            Route::put('/pages/{page}', [PageBuilderController::class, 'update'])->name('update');
            Route::delete('/pages/{page}', [PageBuilderController::class, 'destroy'])->name('destroy');
            Route::post('/pages/{page}/sections', [PageBuilderController::class, 'addSection'])->name('sections.store');
            Route::post('/pages/{page}/reorder', [PageBuilderController::class, 'reorder'])->name('sections.reorder');
            Route::put('/sections/{section}', [PageBuilderController::class, 'updateSection'])->name('sections.update');
            Route::post('/sections/{section}/toggle', [PageBuilderController::class, 'toggleSection'])->name('sections.toggle');
            Route::post('/sections/{section}/duplicate', [PageBuilderController::class, 'duplicateSection'])->name('sections.duplicate');
            Route::delete('/sections/{section}', [PageBuilderController::class, 'destroySection'])->name('sections.destroy');
            Route::post('/upload', [PageBuilderController::class, 'upload'])->name('upload');
        });

        //Administration: users and roles (AJAX)
        Route::group(['middleware'=>'can:users.manage'], function(){
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::redirect('/create-user', '/users'); // old address
        });
        Route::group(['middleware'=>'can:roles.manage'], function(){
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        //System: email server, database backup / export / import
        Route::group(['middleware'=>'can:system.mail'], function(){
            Route::get('/system/mail', [\App\Http\Controllers\SystemMailController::class, 'edit'])->name('system.mail');
            Route::put('/system/mail', [\App\Http\Controllers\SystemMailController::class, 'update'])->name('system.mail.update');
            Route::post('/system/mail/test', [\App\Http\Controllers\SystemMailController::class, 'test'])->middleware('throttle:10,1')->name('system.mail.test');
        });
        Route::group(['middleware'=>'can:system.backup'], function(){
            Route::get('/system/backups', [\App\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
            Route::post('/system/backups', [\App\Http\Controllers\BackupController::class, 'create'])->name('backups.create');
            Route::get('/system/backups/uploads-zip', [\App\Http\Controllers\BackupController::class, 'uploadsZip'])->name('backups.uploads');
            Route::post('/system/backups/import', [\App\Http\Controllers\BackupController::class, 'import'])->middleware('throttle:5,1')->name('backups.import');
            Route::get('/system/backups/{name}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backups.download');
            Route::post('/system/backups/{name}/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->middleware('throttle:5,1')->name('backups.restore');
            Route::delete('/system/backups/{name}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backups.destroy');
        });

        //My account (every logged-in user)
        Route::get('/account', [UserController::class, 'account'])->name('account');
        Route::put('/account', [UserController::class, 'updateAccount'])->name('account.update');

        //Sign Out
        Route::delete('/logout', [CustomAuthController::class, 'logout'])->name('logout');
    });
});


//Resized images for galleries / sliders (made once, then served from upload/cache)
Route::get('/media/thumb/{width}/{path}', [\App\Http\Controllers\MediaController::class, 'thumb'])
    ->whereNumber('width')->where('path', '(assets|upload)/.+\.(jpe?g|png|webp|gif|JPE?G|PNG)');

//Website › Documents: a PDF at its own address (e.g. /Itenary-2026). Unknown names fall through to the pages below.
Route::get('/{slug}/file', [\App\Http\Controllers\DocumentController::class, 'file'])->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]*')->middleware('throttle:120,1,document-file')->name('document.file');
Route::get('/{slug}', [\App\Http\Controllers\DocumentController::class, 'show'])->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]*')->middleware('throttle:120,1,document')->name('document.show');

//Pages created in Admin > Pages (e.g. /speakers) are served by the route above: DocumentController@show hands every
//name that is not a document to FrontendController@customPage. These must stay the LAST routes.
