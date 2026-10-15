<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrontendController extends Controller
{
    public function home(){
        return $this->page('home', 'frontend.layouts.master');
    }
    
    //Vietname-Conference
    public function vietnameConference(){
      return view('frontend.pages.vietname-events-2025');
    }
    
    public function bicsOverview(){
        return $this->page('bics-overview', 'frontend.pages.bics-overview');
    }

    public function upcommingBics(){
        return $this->page('upcomming-bics', 'frontend.pages.upcomming-bics');
    }

    public function libraryResearch(){
        return $this->page('library-research', 'frontend.pages.library-research');
    }

    public function contact(){
        return $this->page('contact-us', 'frontend.pages.contact');
    }

    public function gallery(){
        return $this->page('gallery', 'frontend.pages.gallery');
    }

    public function media(){
        return $this->page('media', 'frontend.pages.media');
    }

    public function registration(){
        $data = ['registration' => \App\Support\EventSettings::registration()];

        // Drop-downs from Admin > Form Options
        if (\App\Models\FormOption::tableReady()) {
            $professions = \App\Models\FormOption::professions();
            return view('frontend.pages.registration', $data + [
                'countries' => \App\Models\FormOption::active('country')->map(fn ($c) => ['name' => $c->label, 'dial' => (string) $c->extra])->values(),
                'titles' => \App\Models\FormOption::active('title')->pluck('label'),
                'professions' => $professions->map(fn ($p) => (object) ['id' => $p->id, 'profession' => $p->label])->values(),
                'specialities' => $professions->mapWithKeys(fn ($p) => [$p->id => $p->children->pluck('label')->values()]),
                'idTypes' => \App\Models\FormOption::active('id_type')->pluck('label'),
            ]);
        }

        // Before the form options migration: the original tables
        $lists = DB::table('dropdownvalue')->get();
        $professions = DB::table('professions')->orderBy('id')->get(['id', 'profession']);
        return view('frontend.pages.registration', $data + [
            'countries' => $lists->filter(fn ($c) => $c->countryName)->map(fn ($c) => ['name' => $c->countryName, 'dial' => $c->dialCode ? '+' . ltrim($c->dialCode, '+') : ''])->values(),
            'titles' => collect(['Dr', 'Prof', 'Mr', 'Mrs', 'Ms']),
            'professions' => $professions,
            'specialities' => $professions->mapWithKeys(fn ($p) => [$p->id => DB::table('specialities')->where('profession_id', $p->id)->orderBy('id')->pluck('speciality')->map(fn ($s) => trim($s))->values()]),
            'idTypes' => $lists->pluck('identification_type')->filter()->unique()->values(),
        ]);
    }
    public function userprofile(Request $request){
        $data = DB::table('registrations')
                ->where('qr_code', $request->query('code'))
                ->first();

        //if data is not found
        if (!$data) {
            abort(404);
        }
        return view('userprofile.profile', compact('data'));
    }
   
    //Sucess Message show after registration successfully completed
    
    public function success(){
        return view('frontend.pages.form-success');
    }

    //Ajax Registration
    public function ajaxregistration(){
        return view('frontend.pages.ajaxregistration');
    }

    //Pages created in Admin > Pages (any other address, e.g. /speakers)
    public function customPage(string $slug){
        $page = Page::findForDisplay($slug);
        if (!$page || $page->is_global) {
            abort(404);
        }
        return view('frontend.page', compact('page'));
    }

    //Show a page from Admin > Pages, or the original template if pages are not set up yet
    private function page(string $slug, string $fallbackView){
        $page = Page::findForDisplay($slug);
        if (!$page) {
            // Missing table (before migrating) -> original view; page switched off -> 404
            return Page::tableReady() ? abort(404) : view($fallbackView);
        }
        return view('frontend.page', compact('page'));
    }
}
