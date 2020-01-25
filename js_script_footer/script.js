// <script type="text/javascript">



    $(document).ready(function () {
        console.log('START....');

        /*СОХРАНЯЕМ ТЕЛЕФОН ДЛЯ ПЕРЕДАЧИ В ФОРМУ НА СЛЕДУЮЩЕЙ СТРАНИЦЕ В КУКИ
        выполняем проверку, включены ли куки
        добавляем теги скрипт для загрузки внешнего сервиса */
        var scriptService = document.createElement('script');
        scriptService.src = "https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js";
        scriptService.type = "text/javascript";
        scriptService.charset = "UTF-8";
        scriptService.onload = function() {
            /*

                1 Перебираем все формы по одной
                2 Вытаскиваем куки, если кук нет - пишем плейсхолдер
                3 Проверяем наличие необходимых полей (Email и тд)
                4 Если поля нет - создаем его скрытым со сначением из кук
                5 Если поле есть - проверяем скрытое ли оно? Если да заполняем куками.

            */

            //выборка всех форм и перебор их по одной

            $( "form" ).each(function( index, element ) {

                var this_cookie;

                //выбираем поле Email из текущей формы
                var email_input = $( element ).find('[name="Email"]');

                //вытаскиваем куки Email, если нет ставим плейсхолдер
                //УБРАЛ ПЛЕЙСХОЛДЕР ПО РЕКОМЕНДАЦИИ РАЗРАБОТЧИКА СКРИПТА-ИНТЕГРАТОРА
                if ($.cookie('cEmail') == undefined) {
                    this_cookie = "";
                }
                else{
                    this_cookie = $.cookie('cEmail');
                }

                //Проверяем, существует ли поле Email.
                //Если нет создаем его скрытым с подствленными значениями из кук
                //Если оно есть, проверяем, чтобы оно было скрытое.
                //Если скрытое, то пишем в поле куки
                if (email_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="Email" tabindex="-1" value="'+this_cookie+'">' );
                }
                else{
                    if (email_input.is('[type="hidden"]')){
                        email_input.val(this_cookie);
                    }
                }

                //Как для Email, только телефон
                var phone_input = $( element ).find('[name="Phone"]');

                if ($.cookie('cPhone') == undefined) {
                    this_cookie = "Телефон не задан";
                }
                else{
                    var ctelRaw = $.cookie('cPhone');

                    console.log('ctelRaw', ctelRaw);

                    // Выбираем из сырой строки номера всё, кроме + и цифр
                    var ctel;
                    ctel = ctelRaw.replace(/[^+0-9]/g, '');

                    // Если первый символ +, то устанавливаем флаг
                    var plus;
                    if (ctel[0] === '+') {
                        plus = true;
                    } else {
                        plus = false;
                    }

                    // Выбираем из сырой строки номера всё, кроме цифр
                    var tel;
                    tel = ctelRaw.replace(/[^0-9]/g, '');

                    console.log('tel', tel);

                    // Усли установлен флаг, то конкатенируем знак плюс
                    if (plus) {
                        this_cookie = '+' + tel;
                    } else {
                        this_cookie = tel;
                    }

                    console.log('this_cookie', this_cookie)
                }

                if (phone_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="Phone" tabindex="-1" value="'+this_cookie+'">' );
                }
                else{
                    if (phone_input.is('[type="hidden"]')){
                        phone_input.val(this_cookie);
                    }
                }

                //Как для Email, только имя
                var name_input = $( element ).find('[name="Name"]');

                if ($.cookie('cName') == undefined) {
                    // console.log('cname='+this_cookie);
                    this_cookie = "";
                }
                else{
                    this_cookie = $.cookie('cName');
                }

                if (name_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="Name" tabindex="-1" value="'+this_cookie+'">' );
                }
                else {
                    if (name_input.is('[type="hidden"]')){
                        name_input.val(this_cookie);
                    }
                }

            });

            //Перебор форм из корзин.
            //Принцип как с кодом выше, только на порле tel и только для корзин
            $(".t706").find("form").each(function( index, element ) {

                var this_cookie;

                $( element ).find('[type="hidden"][name="Phone"]').remove();

                var tel_input = $( element ).find('[name="tel"]');

                if ($.cookie('cTel') == undefined) {
                    this_cookie = "Телефон не задан";
                }
                else {
                    var ctelRaw = $.cookie('cTel');

                    console.log('ctelRaw', ctelRaw);

                    // Выбираем из строки номера всё, кроме + и цифр
                    var ctel;
                    ctel = ctelRaw.replace(/[^+0-9]/g, '');

                    // Усли установлен флаг, то конкатенируем знак плюс
                    var plus;
                    if (ctel[0] === '+') {
                        plus = true;
                    } else {
                        plus = false;
                    }

                    // Выбираем из строки номера всё, кроме цифр
                    var tel;
                    tel = ctelRaw.replace(/[^0-9]/g, '');

                    // Усли установлен флаг, то конкатенируем знак плюс
                    if (plus) {
                        this_cookie = '+' + tel;
                    } else {
                        this_cookie = tel;
                    }

                    console.log('this_cookie', this_cookie);

                }

                if (tel_input.length == 0) {
                    console.log('Add input tel')
                    $( element ).append( '<input type="hidden" name="tel" tabindex="-1" value="'+this_cookie+'">' );
                }
                else {
                    console.log('There is input tel')
                    if (tel_input.is('[type="hidden"]')) {
                        console.log('Type input tel is hidden')
                        tel_input.val(this_cookie);
                    }
                }

            });

        }

        document.documentElement.appendChild(scriptService);

        window.savePhone = function($form) {

            console.log('savePhone');
            console.log('$form', $form);

            //Ловим атрибуты данных формы
            var dataAtr = $($form).data();

            console.log('dataAtr', dataAtr);

            //Получаем телефон с формы которую отправили

            console.log('$($form).find([name="Phone"])', $($form).find('[name="Phone"]'));
            console.log('$($form).find([name="Email"])', $($form).find('[name="Email"]'));
            console.log('$($form).find([name="Name"])', $($form).find('[name="Name"]'));

            var telRaw = $($form).find('[name="Phone"]').val();
            var Email = $($form).find('[name="Email"]').val();
            var cname = $($form).find('[name="Name"]').val();

            console.log('telRaw Телефон с формы', telRaw);

            var tel;
            tel = telRaw.replace(/[^+0-9]/g, "");

            console.log('Телефон: первая выборка', tel);

            console.log('tel[0]', tel[0])

            var plus;
            if (tel[0] === '+') {
                plus = true;
            } else {
                plus = false;
            }

            tel = telRaw.replace(/[^0-9]/g, "");

            console.log('Телефон: вторая выборка', tel);

            if (plus) {
                tel = '+' + tel;
            }

            console.log('tel Телефон в конце обработки', tel);

            // console.log($($form));
            $.cookie('test_cookie', 'cookie_value', { path: '/' });
            if ($.cookie('test_cookie') == 'cookie_value') {

                $.cookie('cPhone', tel , { expires: 99999999999});
                $.cookie('cTel', tel , { expires: 99999999999});
                $.cookie('cEmail', Email , { expires: 99999999999});
                $.cookie('cName', cname , { expires: 99999999999});

                console.log('Сохранение в куки')

                // $.cookie('cPhone', tel , { expires: 30});
                // $.cookie('cTel', tel , { expires: 30});
                // $.cookie('cEmail', Email , { expires: 30});
                // $.cookie('cName', cname , { expires: 30});

                if (dataAtr.successUrl != undefined) {
                    document.location.href = dataAtr.successUrl;
                }

            } else {
                document.location.href = 'https://comfort-academy.ru//thankyou_nocookies';
            }
        };

        //Перебираем все формы по одной и ставим коллбек на сохранение кук
        $( ".js-form-proccess" ).each(function( index, element ) {
            $( element ).data('success-callback', 'window.savePhone');
        });
    });

// </script>
