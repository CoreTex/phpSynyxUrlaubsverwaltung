<?php
namespace CoreTex\APIWrapper;

class Synyx {
    private $public_holidays;
    private $absences;

    protected $api_user;
    protected $api_password;
    protected $api_url;
    protected $sessionId;
    protected $personId;

    public function __construct(string $api_url, string $api_user, string $api_password) {
        $this->api_user = $api_user;
        $this->api_password = $api_password;
        $this->api_url = $api_url;

        //initial data collection
        $this->login();
        $this->getpersonId();
        $this->loadPublicHolidays();
        $this->loadabsences();
    }

    private function getpersonId() {
        $response = \Httpful\Request::get(str_replace('/api','/web/overview',$this->api_url))         // Build a PUT request
             ->addHeader('Cookie', $this->sessionId)
             ->send();                                   // and finally, fire that thing off!.
        preg_match_all('#/(\d+)/#im', $response->headers['location'], $matches);
        $this->personId = $matches[1][0];
    }

    private function login() {
        $data="username=".urlencode($this->api_user)."&password=".urlencode($this->api_password);
        $response = \Httpful\Request::post(str_replace('/api','/login',$this->api_url))
            ->method(\Httpful\Http::POST)
            ->withoutStrictSsl()
            ->body($data)
            ->sendsType(\Httpful\Mime::FORM)
            ->send();
        $cookie = $response->headers['set-cookie'];
        preg_match_all('/^\s*([^;]*)/mi', $cookie, $matches);
        $this->sessionId = $matches[1][0];
    }

    private function loadPublicHolidays() {
        // load timeular data since defined start in ACCOUNTING_START_DATE
         $response = \Httpful\Request::get($this->api_url.'/holidays?year=' . date('Y'))         // Build a PUT request
             ->addHeader('Cookie', $this->sessionId)
             ->send();                                   // and finally, fire that thing off!.
         $this->public_holidays = $response->body->response->publicHolidays;
    }

    private function loadabsences() {
        $response = \Httpful\Request::get($this->api_url.'/absences?person=' . $this->personId . '&year=' . date('Y'))
            ->addHeader('Cookie', $this->sessionId)
            ->send();
        $this->absences = $response->body->response->absences;
    }


    /**
     * @param string $date Y-m-d e.g. 2018-01-01
     * @return float daylength factor
     */
    public function isPublicHoliday(string $date): float {
        // type to correct format if it's not correct
        $date = date('Y-m-d',strtotime($date));
        foreach($this->public_holidays as $phd) {
            if($phd->date == $date) {
                return 1.0 - floatval($phd->dayLength);
            }
        }
        return 0.0;
    }

    public function getPublicHolidayName(string $date): string {
        // type to correct format if it's not correct
        $date = date('Y-m-d',strtotime($date));
        foreach($this->public_holidays as $phd) {
            if($phd->date == $date) {
                return $phd->description;
            }
        }
        return "";
    }

    /**
     * @param string $date Y-m-d e.g. 2018-01-01
     * @return float daylength factor
     */
    public function isVacation($date) {
        // type to correct format if it's not correct
        $date = date('Y-m-d',strtotime($date));
        foreach($this->absences as $abs) {
            if($abs->date == $date && $abs->type == "VACATION") {
                return floatval($abs->dayLength);
            }
        }
        return 0.0;
    }

    /**
     * @param string $date Y-m-d e.g. 2018-01-01
     * @return float daylength factor
     */
    public function isSickLeave($date) {
        // type to correct format if it's not correct
        $date = date('Y-m-d',strtotime($date));
        foreach($this->absences as $abs) {
            if($abs->date == $date && $abs->type == "SICK_NOTE" && $abs->status == "ACTIVE") {
                return floatval($abs->dayLength);
            }
        }
        return 0.0;
    }
}
