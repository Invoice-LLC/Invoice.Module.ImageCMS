
<div class="control-group">
    <label class="control-label" for="inputRecCount">API Key :</label>
    <div class="controls">
        <input type="text" name="payment_method_invoice[api_key]" value="{echo $data['api_key']}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="inputRecCount">Логин :</label>
    <div class="controls">
        <input type="text" name="payment_method_invoice[login]" value="{echo $data['login']}"/>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="inputRecCount">Имя терминала :</label>
    <div class="controls">
        <input type="text" name="payment_method_invoice[default_terminal_name]" value="{echo $data['default_terminal_name']}"/>
    </div>
</div>
