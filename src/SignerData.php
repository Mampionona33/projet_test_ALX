<?php
class SignerData {
    /**
     * @var string
     */
    private $lastname;
    /**
     * @var string
     */
    private $firstname;
    /**
     * @var string
     */
    private $email;
     /**
     * @var string
     */
    private $city;
     /**
     * @var string
     */
    private $phone;

    /**
     * @param string $lastname
     * @param string $firstname
     * @param string $email
     * @param string $city
     * @param string $phone
     */
    public function __construct($lastname, $firstname, $email, $city, $phone) {
        $this->lastname = $lastname;
        $this->firstname = $firstname;
        $this->email = $email;
        $this->city = $city;
        $this->phone = $phone;
    }

    /**
     * @return array<string, string>
     */
    public function getData(): array {
        return array(
            'lastname' => $this->lastname,
            'firstname' => $this->firstname,
            'email' => $this->email,
            'city' => $this->city,
            'phone' => $this->phone
        );
    }
}


?>