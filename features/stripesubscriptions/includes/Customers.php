<?php

namespace CobraAI\Features\StripeSubscriptions;

class Customers
{

    private $feature;
 
 

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        
    }
    /* get_customer
    * @param $customer_id
    * @return $customer
    */
    public function get_customer($id)
    {
        // get user by id with all meta
        $user = get_userdata($id);
        
        return $user;
    }
/* get customer by stripe id
    * @param $customer_id
    * @return $customer
    */
    public function get_customer_by_stripe_id($customer_id){
        // find user  by stripe id 
        $user = get_users(array(
            'meta_key' => 'stripe_customer_id',
            'meta_value' => $customer_id,
            'number' => 1,
            'count_total' => false
        ));
        return $user;
    }
}