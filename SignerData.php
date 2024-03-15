class SignerData {
    private $lastname;
    private $firstname;
    private $email;
    private $city;
    private $phone;

    public function __construct($lastname, $firstname, $email, $city, $phone) {
        $this->lastname = $lastname;
        $this->firstname = $firstname;
        $this->email = $email;
        $this->city = $city;
        $this->phone = $phone;
    }

    public function getData() {
        return array(
            'lastname' => $this->lastname,
            'firstname' => $this->firstname,
            'email' => $this->email,
            'city' => $this->city,
            'phone' => $this->phone
        );
    }
}