// <script type="text/javascript">



    $(document).ready(function () {
        console.log('QWE....');

        /*СОХРАНЯЕМ ТЕЛЕФОН ДЛЯ ПЕРЕДАЧИ В ФОРМУ НА СЛЕДУЮЩЕЙ СТРАНИЦЕ В КУКИ
        выполняем проверку, включены ли куки
        добавляем теги скрипт для загрузки внешнего сервиса */
        var scriptService = document.createElement('script');
        scriptService.src = "https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js";
        scriptService.type = "text/javascript";
        scriptService.charset = "UTF-8";
        scriptService .onload = function() {
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
                };

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
                    };
                };

                //Как для Email, только телефон
                var phone_input = $( element ).find('[name="Phone"]');

                if ($.cookie('cPhone') == undefined) {
                    this_cookie = "Телефон не задан";
                }
                else{
                    this_cookie = $.cookie('cPhone');
                };

                if (phone_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="Phone" tabindex="-1" value="'+this_cookie+'">' );
                }
                else{
                    if (phone_input.is('[type="hidden"]')){
                        phone_input.val(this_cookie);
                    };
                };

                //Как для Email, только имя
                var name_input = $( element ).find('[name="Name"]');

                if ($.cookie('cName') == undefined) {
                    console.log('cname='+this_cookie);
                    this_cookie = "";

                }
                else{
                    this_cookie = $.cookie('cName');
                };

                if (name_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="Name" tabindex="-1" value="'+this_cookie+'">' );
                }
                else{
                    if (name_input.is('[type="hidden"]')){
                        name_input.val(this_cookie);
                    };
                };

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
                else{
                    this_cookie = $.cookie('cTel');
                };

                if (tel_input.length == 0) {
                    $( element ).append( '<input type="hidden" name="tel" tabindex="-1" value="'+this_cookie+'">' );
                }
                else{
                    if (tel_input.is('[type="hidden"]')){
                        tel_input.val(this_cookie);
                    };
                };

            });

        };
        document.documentElement.appendChild(scriptService);

        window.savePhone = function($form) {

            //Ловим атрибуты данных формы
            var dataAtr = $($form).data();

            //Получаем телефон с формы которую отправили
            var tel = $($form).find('[name="Phone"]').val();
            var Email = $($form).find('[name="Email"]').val();
            var cname = $($form).find('[name="Name"]').val();

            console.log($($form));
            $.cookie('test_cookie', 'cookie_value', { path: '/' });
            if ($.cookie('test_cookie') == 'cookie_value') {

                $.cookie('cPhone', tel , { expires: 99999999999});
                $.cookie('cTel', tel , { expires: 99999999999});
                $.cookie('cEmail', Email , { expires: 99999999999});
                $.cookie('cName', cname , { expires: 99999999999});

                if (dataAtr.successUrl != undefined) {
                    document.location.href = dataAtr.successUrl;
                };

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
