<?php
require_once('IuguRequest.php');

class Iugu extends IuguRequest {
    //API Tokens
    private $TokenTeste;
    private $TokenProducao;
    //token gerado pelo payment_token
    protected $token = null;
    //ID de sua Conta na Iugu
    private $account_id;
    //Valor true para criar tokens de teste.
    private $test = true;
    //Método de Pagamento credit_card (cartão de credito) / bank_slip (boleto) / pix (PIX) / all (Todos)
    private $method = 'credit_card';
    //Items da compra
    private $items = [];
    //Pagador
    private $payer = [];
    //Variaveis customizadas
    private $custom_variables = [];
    //Dias de aplicação do disconto
    private $early_payment_discounts = [];

    /**
     * Inicia a conexão com a API IUGU
     * 
     * @access public
     * @param $account_id string | ID da Conta IUGU
     * @param $TokenTeste string | API Token de teste
     * @param $TokenProducao string | API Token de produção
     * @param $test bool | Modo de teste
     * 
     */
    public function __construct($account_id = null, $TokenTeste = null, $TokenProducao = null, $test = true) {
        if(!$account_id)
            die("ID da conta IUGU necessário!");
        
        if(!$TokenTeste || !$TokenProducao){
            die("Ambos os tokens de produção e Teste devem ser preenchidos!");
        }

        $this->account_id       = $account_id;
        $this->TokenTeste       = $TokenTeste;
        $this->TokenProducao    = $TokenProducao;
        if(is_bool($test))
            $this->test         = $test;
    }

    /**
    * Seta o token Id gerado | Não necessário para CreditCard()
    * @access public
    * @param $token | Número gerado pela verificação do cartão de crédito
    */
    public function setIdToken($token) {
        $this->token = $token;
    }

    /**
    * Recupera o último token gerado
    * @access public
    * @return string
    */
    public function getIdToken() {
        return $this->token;
    }

    /**
    * Seta o método de pagamento
    * @access public
    * @param $method | credit_card or bank_slip or pix or all
    * @return string
    */
    public function setMethod($method) {
        $this->method = $method;
    }

    /**
    * Gera HTTP Basic Auth
    * @access private
    * @return string
    */
    private function Authorization() {
        //Utilizar em nome de usuário seu API token e utilizar senha em branco separados por um dois pontos (:)
        $tokenAPI = ($this->test ? $this->TokenTeste : $this->TokenProducao) . ":";

        return 'Basic ' . base64_encode($tokenAPI);
    }

    /**
     *
     * Criar Token
     *
     * @access public
     * @param $number int | Número do cartão de credito
	 * @param $verification_value int | Código de verificação (CVV)
	 * @param $first_name string | Primeiro nome impresso no cartão
	 * @param $last_name string | Sobrenome impresso no cartão
	 * @param $month int | Mês do vencimento
	 * @param $year int | Ano do vencimento
     * @return array
     *
     */
    public function CreditCard($number, $verification_value, $first_name, $last_name, $month, $year) {
        if($this->method != 'credit_card')
            die("O método de pagamento deve ser 'credit_card' para gerar token de cartão de credito.");

        $data['data'] = array(
            'number' => $number, 
            'verification_value' => $verification_value, 
            'first_name' => $first_name, 
            'last_name' => $last_name, 
            'month' => $month, 
            'year' => $year
        );

        $data['account_id'] = $this->account_id;
        $data['method'] = $this->method;
        $data['test'] = $this->test;

        $req = $this->request('POST', 'payment_token', $data);
        $this->token = $req->id;

        return $req;
    }

    /**
     *
     * Adiciona um novo item
     *
     * @access public
     * @param $description string | Descrição ou nome do produto
	 * @param $quantity int | Quantidade
	 * @param $price_cents int | Preço do produto em centavos
     *
     */
    public function addItem($description, $quantity, $price_cents) {
        if($description == null || $quantity == null || $price_cents == null)
            die("Todos os campos devem ser preenchidos.");

        $item = [
            "description"   => $description,
            "quantity"      => $quantity,
            "price_cents"   => $price_cents
        ];

        array_push($this->items, $item);
    }

    /**
     *
     * Reseta todos os itens
     *
     * @access public
     *
     */
    public function ResetItems() {
        $this->items = [];
    }

    /**
     *
     * Seta um Payer
     *
     * @access public
     * @param $cpf_cnpj string | CNPJ ou CPF do pagador
	 * @param $name string | Nome do pagador
	 * @param $phone_prefix string | Prefixo do telefone
	 * @param $phone string | Telefone do pagador
	 * @param $email string | Email do pagador
	 * @param $street string | Rua do pagador
	 * @param $number string | Número da moradia do pagador
	 * @param $district string | Bairro do pagador
	 * @param $city string | Cidade do pagador
	 * @param $state string | Estado do pagador
	 * @param $zip_code string | CEP do pagador
	 * @param $complement string | Complemento do pagador (Não obrigatório)
	 * @param $country string | País do pagador (Não obrigatório)
     *
     */
    public function setPayer($cpf_cnpj, $name, $phone_prefix, $phone, $email, $street, $number, $district, $city, $state, $zip_code, $complement = '', $country = null) {
        $payer = [
            "cpf_cnpj"      => $cpf_cnpj,
            "name"          => $name,
            "phone_prefix"  => $phone_prefix,
            "phone"         => $phone,
            "email"         => $email,
            "address" => [
                "street"    => $street,
                "number"    => $number,
                "district"  => $district,
                "city"      => $city,
                "state"     => $state,
                "zip_code"  => $zip_code,
                "complement" => $complement
            ]
        ];

        if($country)
            $payer['address']['country'] = $country;

        $this->payer = $payer;
    }
    
    /**
     *
     * Reseta o Payer
     *
     * @access public
     *
     */
    public function ResetPayer() {
        $this->payer = [];
    }

    /**
     *
     * Cobrança direta
     *
     * @access public
     * @param $restrict_payment_method bool | Se true, restringe o método de pagamento da cobrança para o definido em method, que no caso será apenas bank_slip.
	 * @param $customer_id string | ID do Cliente. Utilizado para vincular a Fatura a um Cliente
	 * @param $invoice_id string | ID da Fatura a ser utilizada para pagamento
	 * @param $email string | E-mail do Cliente (não é preenchido caso seja enviado um "invoice_id")
	 * @param $months int | Número de Parcelas (2 até 12), por padrão vem 1. O valor mínino de cada parcela é de R$5,00.
	 * @param $discount_cents int | Valor de desconto, em centavos, aplicado sobre os itens criados em caso de não haver fatura vinculada à chamada.
	 * @param $bank_slip_extra_days int | Define o prazo em dias para o pagamento do boleto. Caso não seja enviado, aplica-se o prazo padrão de 3 dias corridos.
	 * @param $keep_dunning bool | Por padrão, a fatura é cancelada caso haja falha na cobrança, a não ser que este parâmetro seja enviado como "true".
	 * @param $order_id string | Número único que identifica o pedido de compra. Opcional, ajuda a evitar o pagamento da mesma fatura.
	 * @return array
     *
     */
    public function DirectBilling($restrict_payment_method = false, $customer_id = null, $invoice_id = null, $email = null, $months = 1, $discount_cents = 0, $bank_slip_extra_days = null, $keep_dunning = false, $order_id = null) {
        if($this->method != 'credit_card' && $this->method != 'bank_slip')
            die("Method '" . $this->method . "' não é válido para esta operação.");
        
        $tokenAPI = $this->test ? $this->TokenTeste : $this->TokenProducao;

        if($this->method != 'bank_slip'){
                if($this->token != null)
                    $data['token'] = $this->token;
                else 
                    die("Token não pode ser nulo! Para gerar um novo token, utilize a função CreditCard().");
                
                //Não deve ser enviado caso seja credit_card
                $bank_slip_extra_days = null;
        } else {
            $data['method'] = $this->method;
            $data['restrict_payment_method'] = $restrict_payment_method;
            //Caso não seja credit_card é sempre 1
            $months = 1;
        }

        if($invoice_id != null) {
            if($customer_id != null)
                $data['customer_id'] = $customer_id;
            $data['invoice_id'] = $invoice_id;
        } else {
            $data['email'] = $email;
        }

        if($months > 12)
            die("Número de parcelas deve ser inferior ou igual a 12.");
        else if($months >= 2)
            $data['months'] = $months;
        
        $data['discount_cents'] = $discount_cents;
        if($bank_slip_extra_days != null)
            $data['bank_slip_extra_days'] = $bank_slip_extra_days;//Caso não seja enviado, aplica-se o prazo padrão de 3 dias corridos.
        
        //Por padrão, a fatura é cancelada caso haja falha na cobrança, a não ser que este parâmetro seja enviado como "true". Obs: Funcionalidade disponível apenas para faturas criadas no momento da cobrança.
        $data['keep_dunning'] = $keep_dunning;

        if(count($this->items) == 0)
            die("É obrigatorio indicar pelo menos 1 produto.");
        
        $data['items'] = $this->items;

        if($this->method == 'bank_slip'){
            if(count($this->payer) != 0)
                $data['payer'] = $this->payer;
            else
                die('Pagador é necessário para esse tipo de transação.');
            
        }

        if($order_id != null ){
            if($order_id <= 0)
                die('ID do pedido deve ser maior e/ou diferente de 0.');
            $data['order_id'] = $order_id;
        }
        
        $req = $this->request('POST', 'charge?api_token=' . $tokenAPI, $data);

        return $req;
    }

    /**
     * Setando variáveis customizadas
     * 
     * @access public
     * @param $name string | Nome da variável
     * @param $value string | Valor atribuido a variável
     * 
     */
    public function SetCustomVariables($name, $value) {
        if($name == null || $value == null)
            die("Todos os campos devem ser preenchidos.");
        
        $custom_variables = ["name" => $name, "value" => $value];

        array_push($this->custom_variables, $custom_variables);
    }

    /**
     *
     * Reseta todas as variaveis
     *
     * @access public
     *
     */
    public function ResetCustomVariables() {
        $this->custom_variables = [];
    }

    /**
     * Setando a quantidade de dias de antecedência para o pagamento receber o desconto (Se enviado, substituirá a configuração atual da conta)
     * 
     * @access public
     * @param $days int | Número de dias antes do vencimento para aplicação do desconto.
     * @param $percent float | Valor do desconto em porcentagem. Não pode ser usado com value_cents.
     * @param $value_cents int | Valor do desconto em centavos. Não pode ser usado com percent.
     * 
     */
    public function SetEarlyPaymentDiscounts($days, $percent, $value_cents) {
        if($days == null || $percent == null || $value_cents == null)
            die("Todos os campos devem ser preenchidos.");
        
        $early_payment_discounts = ["days" => $days, "percent" => $percent, "value_cents" => $value_cents];

        array_push($this->early_payment_discounts, $early_payment_discounts);
    }

    /**
     *
     * Reseta todos os dias para desconto de pagamento antecipado
     *
     * @access public
     *
     */
    public function ResetEarlyPaymentDiscounts() {
        $this->early_payment_discounts = [];
    }

    /**
     *
     * Cria Faturamento
     *
     * @access public
	 * @param $email string | E-mail do Cliente
     * @param $due_date date | Data do vencimento. (Formato: 'AAAA-MM-DD').
	 * @param $ensure_workday_due_date bool | Se true, garante que a data de vencimento seja apenas em dias de semana, e não em sábados ou domingos.
	 * @param $return_url string | Cliente é redirecionado para essa URL após efetuar o pagamento da Fatura pela página de Fatura da Iugu
	 * @param $expired_url string | Cliente é redirecionado para essa URL se a Fatura que estiver acessando estiver expirada
	 * @param $notification_url string | URL chamada para todas as notificações de Fatura, assim como os webhooks (Gatilhos) são chamados
	 * @param $ignore_canceled_email bool | Desliga o e-mail de cancelamento de fatura
	 * @param $fines bool | Booleano para Habilitar ou Desabilitar multa por atraso de pagamento
	 * @param $late_payment_fine int | Determine a multa % a ser cobrada para pagamentos efetuados após a data de vencimento
	 * @param $per_day_interest bool | Booleano que determina se cobra ou não juros por dia de atraso. 1% ao mês pro rata. Necessário passar a multa como true
	 * @param $per_day_interest_value int | Informar o valor percentual de juros que deseja cobrar
	 * @param $discount_cents int | Valor dos Descontos em centavos
	 * @param $customer_id string | ID do Cliente
     * @param $ignore_due_email bool | Booleano que ignora o envio do e-mail de cobrança
     * @param $subscription_id string | Amarra esta Fatura com a Assinatura especificada. Esta fatura não causa alterações na assinatura vinculada.
     * @param $credits int | Caso tenha o 'subscription_id', pode-se enviar o número de créditos a adicionar nessa Assinatura baseada em créditos, quando a Fatura for paga.
     * @param $early_payment_discount bool | Ativa ou desativa os descontos por pagamento antecipado. Quando true, sobrepõe as configurações de desconto da conta.
     * @param $order_id string | Informações do "payer" são obrigatórias para a emissão de boletos ou necessárias para seu sistema de antifraude. Para emissão de Pix apenas o "payer.name" é obrigatório.
	 * @return array
     *
     */
    public function CreateInvoice($email = null, $due_date = null, $ensure_workday_due_date = true, $return_url = '', $expired_url = '', $notification_url = '', $ignore_canceled_email = false, $fines = false, $late_payment_fine = 0, $per_day_interest = false, $per_day_interest_value = 0, $discount_cents = 0, $customer_id = null, $ignore_due_email = false, $subscription_id = null, $credits = 0, $early_payment_discount = false, $order_id = null) {
        if($this->method != "all" && $this->method != "credit_card" && $this->method != "bank_slip" && $this->method != "pix")
            die("Method '" . $this->method . "' não é válido para esta operação.");
        
        $tokenAPI = $this->test ? $this->TokenTeste : $this->TokenProducao;
        
        $data['payable_with'] = $this->method;
        
        if($email == null || !preg_match('/@/', $email))
            die("E-mail inválido ou nulo!");

        $data['email'] = $email;
        
        if (!DateTime::createFromFormat('Y-m-d', $due_date))
           die("Data com formato inválido! Certo: YYYY-MM-DD");

        $data['due_date'] = $due_date;
        
        if(!is_bool($ensure_workday_due_date))
            die("Valor deve ser bool para o 3º parâmetro");

        $data['ensure_workday_due_date'] = $ensure_workday_due_date;

        if($return_url != '')
            $data['return_url'] = $return_url;

        if($expired_url != '')
            $data['expired_url'] = $expired_url;

        if($notification_url != '')
            $data['notification_url'] = $notification_url;

        if(!is_bool($ignore_canceled_email))
            die("Valor deve ser bool para o 7º parâmetro");

        $data['ignore_canceled_email'] = $ignore_canceled_email;

        if(!is_bool($fines))
            die("Valor deve ser bool para o 8º parâmetro");

        if($fines) {
            $data['fines'] = $fines;
            $data['late_payment_fine'] = $late_payment_fine;
            $data['per_day_interest'] = $per_day_interest;
            $data['per_day_interest_value'] = $per_day_interest_value;
        }

        $data['discount_cents'] = $discount_cents ? $discount_cents : 0;

        if($customer_id != null)
            $data['customer_id'] = $customer_id;

        if(!is_bool($ignore_due_email))
            die("Valor deve ser bool para o 15º parâmetro");

        $data['ignore_due_email'] = $ignore_due_email;

        if($subscription_id != null) {
            $data['subscription_id'] = $subscription_id;
            $data['credits'] = $credits ? $credits : 0;
        }

        if(count($this->custom_variables) > 0)
            $data['custom_variables'] = $this->custom_variables;
        
        if(!is_bool($early_payment_discount))
            die("Valor deve ser bool para o 19º parâmetro");
        
        $data['early_payment_discount'] = $early_payment_discount;

        if(count($this->early_payment_discounts) > 0 && $early_payment_discount)
            $data['early_payment_discounts'] = $this->early_payment_discounts;

        if(count($this->items) == 0)
            die("É obrigatorio indicar pelo menos 1 produto.");
        
        $data['items'] = $this->items;

        if($this->method == 'bank_slip' || $this->method == 'pix' || $this->method == 'all'){
            if(count($this->payer) != 0)
                $data['payer'] = $this->payer;
            else
                die('Pagador é necessário para esse tipo de transação.');
        }

        if($order_id != null ){
            if($order_id <= 0)
                die('ID do pedido deve ser maior e/ou diferente de 0.');
            $data['order_id'] = $order_id;
        }

        $req = $this->request('POST', 'invoices?api_token=' . $tokenAPI, $data);

        return $req;
    }

    /**
     * 
     * Lista de gatilhos
     * 
     * @access public
     * @return array
     * 
     */
    public function ListTriggers() {
        $Authorization = $this->Authorization();
        $header = array(
            "Authorization: $Authorization"
        );

        $req = $this->request('GET', 'web_hooks', array(), $header);
        return $req;
    }
    
    /**
     * 
     * Lista os API_Token
     * 
     * @access public
     * @return array
     * 
     */
    public function list_api_token() {
        $Authorization = $this->Authorization();
        $header = array(
            "Authorization: $Authorization"
        );
        
        $req = $this->request('GET', $this->account_id . '/api_tokens', array(), $header);
        return $req;
    }

}