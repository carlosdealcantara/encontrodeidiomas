<?php
/**
 * Migração v11 — Adição de 26 novas perguntas de boas-vindas
 * com traduções completas para: es, pt, de, ru, ja, zh, id, it
 *
 * Execução segura: apenas insere, nunca altera dados existentes.
 */
require_once '../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Migração v11 — 26 novas perguntas de boas-vindas ===\n\n";

    $lastId = $conn->query("SELECT MAX(id) FROM community_welcome_questions")->fetchColumn();
    echo "Último ID antes da migração: $lastId\n\n";

    $new_questions = [
        // 1
        [
            'text_target' => "Qual é uma gíria ou expressão do seu idioma nativo que todo mundo deveria conhecer?",
            'text_en'     => "What's a slang word or expression from your native language that everyone should know?",
            'translations' => [
                'es' => "¿Cuál es una palabra de argot o expresión de tu idioma nativo que todos deberían conocer?",
                'pt' => "Qual é uma gíria ou expressão do seu idioma nativo que todo mundo deveria conhecer?",
                'de' => "Welches Slangwort oder welche Redewendung aus deiner Muttersprache sollte jeder kennen?",
                'ru' => "Какое сленговое слово или выражение из вашего родного языка должны знать все?",
                'ja' => "あなたの母語のスラングや表現で、みんなに知ってほしいものって何かある？",
                'zh' => "你母语里有哪个俚语或表达是你觉得大家都应该知道的？",
                'id' => "Apa kata gaul atau ungkapan dari bahasa aslimu yang menurutmu semua orang harus tahu?",
                'it' => "Qual è una parola gergale o un'espressione della tua lingua madre che tutti dovrebbero conoscere?",
            ],
        ],
        // 2
        [
            'text_target' => "Qual foi o seu maior momento de confusão ou situação embaraçosa por causa de um erro de idioma?",
            'text_en'     => "What was your biggest \"lost in translation\" or embarrassing language mix-up moment?",
            'translations' => [
                'es' => "¿Cuál fue tu mayor momento de confusión o situación vergonzosa por un error en el idioma?",
                'pt' => "Qual foi o seu maior momento de confusão ou situação embaraçosa por causa de um erro de idioma?",
                'de' => "Was war dein peinlichster Moment durch eine sprachliche Verwechslung oder ein Missverständnis?",
                'ru' => "Какой был ваш самый курьёзный или неловкий момент из-за языковой ошибки?",
                'ja' => "言葉の誤解や言い間違いで一番恥ずかしかったエピソードって何？",
                'zh' => "你因为语言误解或说错话而最尴尬的时刻是什么？",
                'id' => "Apa momen paling memalukan atau lucu yang pernah kamu alami karena salah bicara dalam bahasa lain?",
                'it' => "Qual è stato il tuo momento più imbarazzante per un errore linguistico o un malinteso?",
            ],
        ],
        // 3
        [
            'text_target' => "Qual sotaque no idioma que você está aprendendo você acha mais difícil ou mais encantador?",
            'text_en'     => "Which accent in your target language do you find the most challenging or the most charming?",
            'translations' => [
                'es' => "¿Qué acento en el idioma que estás aprendiendo te parece el más difícil o el más encantador?",
                'pt' => "Qual sotaque no idioma que você está aprendendo você acha mais difícil ou mais encantador?",
                'de' => "Welchen Akzent in deiner Zielsprache findest du am schwierigsten oder am charmantesten?",
                'ru' => "Какой акцент в изучаемом вами языке вы находите самым сложным или самым обаятельным?",
                'ja' => "学んでいる言語の中で、一番難しいと思うアクセントと、一番かわいいと思うアクセントってどれ？",
                'zh' => "在你学习的语言里，哪种口音你觉得最难懂，或者最迷人？",
                'id' => "Aksen mana dalam bahasa yang kamu pelajari yang menurutmu paling menantang atau paling menarik?",
                'it' => "Quale accento nella lingua che stai imparando trovi il più difficile o il più affascinante?",
            ],
        ],
        // 4
        [
            'text_target' => "Qual é a diferença cultural mais inusitada ou surpreendente que você já vivenciou pessoalmente?",
            'text_en'     => "What is the most unusual or surprising cultural difference you've experienced firsthand?",
            'translations' => [
                'es' => "¿Cuál es la diferencia cultural más inusual o sorprendente que has experimentado en persona?",
                'pt' => "Qual é a diferença cultural mais inusitada ou surpreendente que você já vivenciou pessoalmente?",
                'de' => "Was ist der ungewöhnlichste oder überraschendste kulturelle Unterschied, den du selbst erlebt hast?",
                'ru' => "Какое самое необычное или удивительное культурное различие вы испытали на собственном опыте?",
                'ja' => "実際に体験した中で、一番びっくりした文化の違いって何だった？",
                'zh' => "你亲身经历过最让你意外或印象最深的文化差异是什么？",
                'id' => "Apa perbedaan budaya paling unik atau mengejutkan yang pernah kamu alami sendiri?",
                'it' => "Qual è la differenza culturale più insolita o sorprendente che hai vissuto di persona?",
            ],
        ],
        // 5
        [
            'text_target' => "Se você pudesse viver em qualquer época histórica ou cidade estrangeira por apenas um mês, quando e onde seria?",
            'text_en'     => "If you could live in any historical era or foreign city for just one month, where and when would it be?",
            'translations' => [
                'es' => "Si pudieras vivir en cualquier época histórica o ciudad extranjera durante solo un mes, ¿cuándo y dónde sería?",
                'pt' => "Se você pudesse viver em qualquer época histórica ou cidade estrangeira por apenas um mês, quando e onde seria?",
                'de' => "Wenn du für einen Monat in einer beliebigen historischen Epoche oder fremden Stadt leben könntest, wann und wo wäre das?",
                'ru' => "Если бы вы могли прожить один месяц в любой исторической эпохе или иностранном городе, когда и где это было бы?",
                'ja' => "もし1ヶ月だけ、歴史上のどんな時代や外国の街でも住めるとしたら、いつ・どこを選ぶ？",
                'zh' => "如果你可以在任意一个历史时期或外国城市生活一个月，你会选择什么时候、在哪里？",
                'id' => "Kalau kamu bisa hidup di era sejarah atau kota asing mana pun selama sebulan, kapan dan di mana itu?",
                'it' => "Se potessi vivere in qualsiasi epoca storica o città straniera per un solo mese, quando e dove sarebbe?",
            ],
        ],
        // 6
        [
            'text_target' => "Qual é um prato típico da sua cidade natal que os visitantes devem experimentar, mas que os estrangeiros normalmente temem?",
            'text_en'     => "What's a local dish from your hometown that visitors must try, but foreigners are usually afraid to?",
            'translations' => [
                'es' => "¿Cuál es un plato local de tu ciudad natal que los visitantes deben probar, pero que los extranjeros suelen temer?",
                'pt' => "Qual é um prato típico da sua cidade natal que os visitantes devem experimentar, mas que os estrangeiros normalmente temem?",
                'de' => "Welches lokale Gericht aus deiner Heimatstadt müssen Besucher unbedingt probieren, auch wenn Ausländer es meist meiden?",
                'ru' => "Какое местное блюдо из вашего родного города туристы обязательно должны попробовать, но иностранцы обычно боятся?",
                'ja' => "地元の料理で、旅行者にはぜひ食べてほしいけど、外国人がちょっと怖がりそうなものって何かある？",
                'zh' => "你家乡有什么当地美食是游客必须尝试、但外国人通常不敢吃的？",
                'id' => "Ada makanan khas daerahmu yang wajib dicoba pengunjung, tapi orang asing biasanya takut mencicipinya?",
                'it' => "Qual è un piatto locale della tua città natale che i visitatori devono assolutamente provare, ma che gli stranieri di solito temono?",
            ],
        ],
        // 7
        [
            'text_target' => "Você é um viajante lento que gosta de se misturar com os locais, ou prefere ver o máximo de lugares possível?",
            'text_en'     => "Are you a slow traveler who likes to blend in, or do you try to see as many places as possible?",
            'translations' => [
                'es' => "¿Eres un viajero lento que le gusta integrarse con los locales, o intentas ver el máximo de lugares posible?",
                'pt' => "Você é um viajante lento que gosta de se misturar com os locais, ou prefere ver o máximo de lugares possível?",
                'de' => "Bist du ein langsamer Reisender, der sich gern einleben möchte, oder versuchst du so viele Orte wie möglich zu sehen?",
                'ru' => "Вы предпочитаете медленные путешествия, чтобы влиться в местную жизнь, или стараетесь посетить как можно больше мест?",
                'ja' => "じっくりその土地に溶け込むタイプ？それともできるだけたくさんの場所を回るタイプ？",
                'zh' => "你是那种喜欢慢慢融入当地生活的旅行者，还是喜欢尽量多去几个地方的那种？",
                'id' => "Kamu tipe yang suka menyatu dengan budaya lokal secara perlahan, atau lebih suka mengunjungi sebanyak mungkin tempat?",
                'it' => "Sei un viaggiatore lento che ama integrarsi con i locali, o cerchi di vedere il maggior numero di posti possibile?",
            ],
        ],
        // 8
        [
            'text_target' => "Qual é um item que você sempre coloca na mala, independentemente de para onde está viajando?",
            'text_en'     => "What's one item you always pack in your bag, no matter where you're traveling?",
            'translations' => [
                'es' => "¿Cuál es un artículo que siempre metes en tu maleta, sin importar a dónde viajes?",
                'pt' => "Qual é um item que você sempre coloca na mala, independentemente de para onde está viajando?",
                'de' => "Welchen Gegenstand packst du immer in deinen Koffer, egal wohin du reist?",
                'ru' => "Какой предмет вы всегда берёте с собой в поездку, куда бы вы ни отправились?",
                'ja' => "どこへ行くときでも必ずバッグに入れるものって何？",
                'zh' => "不管去哪里旅行，你一定会带的一件东西是什么？",
                'id' => "Benda apa yang selalu kamu masukkan ke dalam tas, ke mana pun kamu pergi?",
                'it' => "Qual è un oggetto che metti sempre in valigia, non importa dove stai andando?",
            ],
        ],
        // 9
        [
            'text_target' => "Qual é um filme, série ou podcast no idioma que você está aprendendo que mais te ajudou a melhorar?",
            'text_en'     => "What's a movie, series, or podcast in your target language that helped you improve the most?",
            'translations' => [
                'es' => "¿Cuál es una película, serie o pódcast en el idioma que estás aprendiendo que más te ha ayudado a mejorar?",
                'pt' => "Qual é um filme, série ou podcast no idioma que você está aprendendo que mais te ajudou a melhorar?",
                'de' => "Welcher Film, welche Serie oder welcher Podcast in deiner Zielsprache hat dir am meisten geholfen, dich zu verbessern?",
                'ru' => "Какой фильм, сериал или подкаст на изучаемом вами языке помог вам больше всего в его освоении?",
                'ja' => "学んでいる言語の映画・ドラマ・ポッドキャストで、一番上達につながったものって何？",
                'zh' => "在你学习的语言里，哪部电影、剧集或播客对你进步帮助最大？",
                'id' => "Film, serial, atau podcast dalam bahasa yang kamu pelajari yang paling membantu kemajuanmu apa?",
                'it' => "Qual è un film, una serie o un podcast nella lingua che stai imparando che ti ha aiutato di più a migliorare?",
            ],
        ],
        // 10
        [
            'text_target' => "Você prefere aprender por meio de estudo estruturado, como livros e aplicativos, ou pelo caos total da imersão e das conversas com estranhos?",
            'text_en'     => "Do you prefer learning through structured study, like books and apps, or complete chaos through immersion and talking to strangers?",
            'translations' => [
                'es' => "¿Prefieres aprender mediante el estudio estructurado, con libros y aplicaciones, o a través del caos total de la inmersión y las conversaciones con desconocidos?",
                'pt' => "Você prefere aprender por meio de estudo estruturado, como livros e aplicativos, ou pelo caos total da imersão e das conversas com estranhos?",
                'de' => "Lernst du lieber durch strukturiertes Lernen mit Büchern und Apps oder durch totales Chaos der Immersion und Gespräche mit Fremden?",
                'ru' => "Вы предпочитаете учиться через структурированные занятия — книги и приложения, или через полный хаос погружения и разговоров с незнакомцами?",
                'ja' => "教科書やアプリでしっかり勉強するのが好き？それともネイティブと話しまくる超immersionスタイル派？",
                'zh' => "你更喜欢通过书本和应用程序系统学习，还是直接跳进去跟陌生人说话、全靠沉浸式体验？",
                'id' => "Kamu lebih suka belajar dengan cara terstruktur seperti buku dan aplikasi, atau langsung terjun dalam kekacauan imersi dan ngobrol sama orang asing?",
                'it' => "Preferisci imparare attraverso lo studio strutturato, come libri e app, o attraverso il caos totale dell'immersione e delle conversazioni con sconosciuti?",
            ],
        ],
        // 11
        [
            'text_target' => "Qual é uma música em um idioma que você não entende, mas que você ainda canta junto com total confiança?",
            'text_en'     => "What's a song in a language you don't understand, but you still sing along to with total confidence?",
            'translations' => [
                'es' => "¿Cuál es una canción en un idioma que no entiendes, pero que igual cantas con total confianza?",
                'pt' => "Qual é uma música em um idioma que você não entende, mas que você ainda canta junto com total confiança?",
                'de' => "Welches Lied in einer Sprache, die du nicht verstehst, singst du trotzdem mit voller Überzeugung mit?",
                'ru' => "Какую песню на языке, который вы не понимаете, вы всё равно поёте с полной уверенностью?",
                'ja' => "意味はわからないけど、なぜか自信満々で一緒に歌っちゃう外国語の曲って何？",
                'zh' => "有没有一首你完全听不懂的外语歌，但你还是超有自信地跟着唱？",
                'id' => "Ada lagu dalam bahasa yang kamu nggak ngerti, tapi kamu tetap ikut nyanyi dengan penuh percaya diri?",
                'it' => "C'è una canzone in una lingua che non capisci, ma che canti comunque con tutta la sicurezza del mondo?",
            ],
        ],
        // 12
        [
            'text_target' => "Se você fosse convidado para dar uma palestra TED de 10 minutos sobre um tema que ama, sem nenhuma preparação, qual seria o tema?",
            'text_en'     => "If you were invited to give a 10-minute TED Talk on a topic you love with zero preparation, what would it be?",
            'translations' => [
                'es' => "Si te invitaran a dar una charla TED de 10 minutos sobre un tema que amas, sin ninguna preparación, ¿cuál sería?",
                'pt' => "Se você fosse convidado para dar uma palestra TED de 10 minutos sobre um tema que ama, sem nenhuma preparação, qual seria o tema?",
                'de' => "Wenn du eingeladen würdest, einen 10-minütigen TED Talk über ein Thema zu halten, das du liebst – ohne jede Vorbereitung – worüber würdest du sprechen?",
                'ru' => "Если бы вас пригласили прочитать 10-минутный TED Talk на тему, которую вы обожаете, без какой-либо подготовки, что бы это было?",
                'ja' => "もし準備ゼロで10分間のTEDトークをやるとしたら、何のテーマで話す？",
                'zh' => "如果你被邀请做一个10分钟的TED演讲，主题是你热爱的事情，完全不用准备，你会讲什么？",
                'id' => "Kalau kamu diundang untuk memberikan TED Talk 10 menit tentang topik yang kamu sukai tanpa persiapan sama sekali, apa topiknya?",
                'it' => "Se fossi invitato a fare un TED Talk di 10 minuti su un argomento che ami, senza alcuna preparazione, di cosa parleresti?",
            ],
        ],
        // 13
        [
            'text_target' => "Qual é uma coisa pequena e cotidiana do seu país que parece totalmente normal para você, mas espanta os estrangeiros?",
            'text_en'     => "What is a small, everyday thing in your country that seems totally normal to you, but blows foreigners' minds?",
            'translations' => [
                'es' => "¿Cuál es una pequeña cosa cotidiana de tu país que te parece completamente normal, pero que deja a los extranjeros con la boca abierta?",
                'pt' => "Qual é uma coisa pequena e cotidiana do seu país que parece totalmente normal para você, mas espanta os estrangeiros?",
                'de' => "Was ist eine kleine, alltägliche Sache in deinem Land, die dir völlig normal erscheint, aber Ausländer zum Staunen bringt?",
                'ru' => "Что такое маленькое и повседневное в вашей стране, что кажется вам абсолютно нормальным, но поражает иностранцев?",
                'ja' => "自分の国では当たり前なのに、外国人がびっくりする小さな日常のことって何かある？",
                'zh' => "在你的国家里，有什么日常小事对你来说完全正常，但却让外国人大开眼界？",
                'id' => "Ada hal kecil sehari-hari di negaramu yang menurutmu sangat normal, tapi justru bikin orang asing tercengang?",
                'it' => "C'è una piccola cosa quotidiana nel tuo paese che ti sembra del tutto normale, ma che lascia gli stranieri a bocca aperta?",
            ],
        ],
        // 14
        [
            'text_target' => "Você é uma pessoa madrugadora ou coruja da noite, e como isso afeta a sua rotina de prática do idioma?",
            'text_en'     => "Are you an early bird or a night owl, and how does that affect your language practice routine?",
            'translations' => [
                'es' => "¿Eres una persona madrugadora o un búho nocturno, y cómo afecta eso a tu rutina de práctica del idioma?",
                'pt' => "Você é uma pessoa madrugadora ou coruja da noite, e como isso afeta a sua rotina de prática do idioma?",
                'de' => "Bist du eine Frühaufsteherin oder eine Nachteule, und wie beeinflusst das deine Sprachlernroutine?",
                'ru' => "Вы жаворонок или сова, и как это влияет на вашу практику языка?",
                'ja' => "朝型？夜型？それって語学の練習ルーティンにどんな影響あってる？",
                'zh' => "你是早起型还是夜猫子？这对你的语言练习有什么影响？",
                'id' => "Kamu orang yang suka bangun pagi atau begadang, dan bagaimana itu mempengaruhi rutinitas belajar bahasamu?",
                'it' => "Sei un'allodola mattutina o un gufo notturno, e come influisce sulla tua routine di pratica della lingua?",
            ],
        ],
        // 15
        [
            'text_target' => "Qual é uma habilidade ou hobby que você sempre quis aprender, completamente diferente de idiomas?",
            'text_en'     => "What's a skill or hobby you've always wanted to learn, completely unrelated to languages?",
            'translations' => [
                'es' => "¿Cuál es una habilidad o hobby que siempre has querido aprender, completamente ajeno a los idiomas?",
                'pt' => "Qual é uma habilidade ou hobby que você sempre quis aprender, completamente diferente de idiomas?",
                'de' => "Welche Fähigkeit oder welches Hobby hast du immer lernen wollen, die absolut nichts mit Sprachen zu tun hat?",
                'ru' => "Какой навык или хобби вы всегда хотели освоить, совершенно не связанный с языками?",
                'ja' => "語学とは全く関係なく、ずっとやってみたかったスキルや趣味って何？",
                'zh' => "你一直想学的、跟语言完全没关系的技能或爱好是什么？",
                'id' => "Apa keahlian atau hobi yang selalu ingin kamu pelajari, yang sama sekali tidak berhubungan dengan bahasa?",
                'it' => "C'è un'abilità o un hobby che hai sempre voluto imparare, completamente scollegato dalle lingue?",
            ],
        ],
        // 16
        [
            'text_target' => "Se você pudesse convidar qualquer pessoa do mundo para jantar, quem seria?",
            'text_en'     => "If you could invite anyone in the world to dinner, who would it be?",
            'translations' => [
                'es' => "Si pudieras invitar a cualquier persona del mundo a cenar, ¿quién sería?",
                'pt' => "Se você pudesse convidar qualquer pessoa do mundo para jantar, quem seria?",
                'de' => "Wenn du irgendeine Person der Welt zum Abendessen einladen könntest, wen würdest du einladen?",
                'ru' => "Если бы вы могли пригласить кого угодно в мире на ужин, кто бы это был?",
                'ja' => "世界中の誰でも一人、ディナーに招待できるとしたら誰にする？",
                'zh' => "如果你可以邀请世界上任何一个人共进晚餐，你会邀请谁？",
                'id' => "Kalau kamu bisa mengundang siapa saja di dunia ini untuk makan malam, kamu akan mengundang siapa?",
                'it' => "Se potessi invitare qualsiasi persona al mondo a cena, chi sarebbe?",
            ],
        ],
        // 17
        [
            'text_target' => "Você gostaria de ser famoso? De que forma?",
            'text_en'     => "Would you like to be famous? In what way?",
            'translations' => [
                'es' => "¿Te gustaría ser famoso? ¿De qué manera?",
                'pt' => "Você gostaria de ser famoso? De que forma?",
                'de' => "Würdest du gerne berühmt sein? Auf welche Art?",
                'ru' => "Хотели бы вы быть знаменитым? Каким образом?",
                'ja' => "有名になりたいと思う？どんなふうに有名になりたい？",
                'zh' => "你想出名吗？想以什么方式出名？",
                'id' => "Apakah kamu ingin menjadi terkenal? Dengan cara apa?",
                'it' => "Ti piacerebbe essere famoso? In che modo?",
            ],
        ],
        // 18
        [
            'text_target' => "Como seria um dia perfeito para você?",
            'text_en'     => "What would a perfect day look like to you?",
            'translations' => [
                'es' => "¿Cómo sería un día perfecto para ti?",
                'pt' => "Como seria um dia perfeito para você?",
                'de' => "Wie würde ein perfekter Tag für dich aussehen?",
                'ru' => "Как выглядел бы для вас идеальный день?",
                'ja' => "あなたにとって最高の一日って、どんな感じ？",
                'zh' => "对你来说，完美的一天会是什么样的？",
                'id' => "Seperti apa hari yang sempurna bagimu?",
                'it' => "Come sarebbe una giornata perfetta per te?",
            ],
        ],
        // 19
        [
            'text_target' => "Quando foi a última vez que você cantou para si mesmo? E para outra pessoa?",
            'text_en'     => "When was the last time you sang to yourself? What about to someone else?",
            'translations' => [
                'es' => "¿Cuándo fue la última vez que cantaste para ti mismo? ¿Y para otra persona?",
                'pt' => "Quando foi a última vez que você cantou para si mesmo? E para outra pessoa?",
                'de' => "Wann hast du zuletzt für dich selbst gesungen? Und für jemand anderen?",
                'ru' => "Когда вы в последний раз пели сами для себя? А для кого-то другого?",
                'ja' => "最後に一人でガーッと歌ったのはいつ？誰かのために歌ったのは？",
                'zh' => "你上一次给自己唱歌是什么时候？给别人唱呢？",
                'id' => "Kapan terakhir kali kamu bernyanyi untuk diri sendiri? Lalu untuk orang lain?",
                'it' => "Quando hai cantato l'ultima volta per te stesso? E per qualcun altro?",
            ],
        ],
        // 20
        [
            'text_target' => "Se você pudesse mudar alguma coisa sobre como foi criado, o que seria?",
            'text_en'     => "If you could change anything about how you were raised, what would it be?",
            'translations' => [
                'es' => "Si pudieras cambiar algo sobre cómo te criaron, ¿qué sería?",
                'pt' => "Se você pudesse mudar alguma coisa sobre como foi criado, o que seria?",
                'de' => "Wenn du etwas an deiner Erziehung ändern könntest, was wäre das?",
                'ru' => "Если бы вы могли что-то изменить в своём воспитании, что бы это было?",
                'ja' => "自分の育ち方について、もし何か一つ変えられるとしたら何にする？",
                'zh' => "如果你可以改变自己成长过程中的某件事，你会改变什么？",
                'id' => "Kalau kamu bisa mengubah sesuatu tentang cara kamu dibesarkan, apa itu?",
                'it' => "Se potessi cambiare qualcosa di come sei stato cresciuto, cosa sarebbe?",
            ],
        ],
        // 21
        [
            'text_target' => "Se você pudesse acordar amanhã com uma nova habilidade ou qualidade, qual seria?",
            'text_en'     => "If you could wake up tomorrow with any new skill or quality, what would it be?",
            'translations' => [
                'es' => "Si pudieras despertar mañana con una nueva habilidad o cualidad, ¿cuál sería?",
                'pt' => "Se você pudesse acordar amanhã com uma nova habilidade ou qualidade, qual seria?",
                'de' => "Wenn du morgen mit einer neuen Fähigkeit oder Eigenschaft aufwachen könntest, was wäre das?",
                'ru' => "Если бы вы могли проснуться завтра с любым новым навыком или качеством, что бы это было?",
                'ja' => "明日目が覚めたら新しいスキルや才能が身についているとしたら、何がいい？",
                'zh' => "如果你明天醒来就拥有了某种新技能或品质，你希望是什么？",
                'id' => "Kalau kamu bisa bangun besok dengan kemampuan atau sifat baru apa pun, apa yang kamu pilih?",
                'it' => "Se potessi svegliarti domani con una nuova abilità o qualità, quale sarebbe?",
            ],
        ],
        // 22
        [
            'text_target' => "Se uma bola de cristal pudesse te dizer a verdade sobre você mesmo, sua vida, o futuro ou qualquer outra coisa, o que você perguntaria?",
            'text_en'     => "If a crystal ball could tell you the truth about yourself, your life, the future, or anything else, what would you ask?",
            'translations' => [
                'es' => "Si una bola de cristal pudiera decirte la verdad sobre ti mismo, tu vida, el futuro o cualquier otra cosa, ¿qué preguntarías?",
                'pt' => "Se uma bola de cristal pudesse te dizer a verdade sobre você mesmo, sua vida, o futuro ou qualquer outra coisa, o que você perguntaria?",
                'de' => "Wenn eine Kristallkugel dir die Wahrheit über dich selbst, dein Leben, die Zukunft oder irgendetwas anderes sagen könnte, was würdest du fragen?",
                'ru' => "Если бы хрустальный шар мог сказать вам правду о вас самих, вашей жизни, будущем или чём-то ещё, что бы вы спросили?",
                'ja' => "もし水晶玉があなた自身のこと、人生、未来など何でも教えてくれるとしたら、何を聞く？",
                'zh' => "如果一个水晶球可以告诉你关于自己、你的生活、未来或其他任何事情的真相，你会问什么？",
                'id' => "Kalau ada bola kristal yang bisa memberitahu kebenaran tentang dirimu, hidupmu, masa depan, atau apa pun, apa yang akan kamu tanyakan?",
                'it' => "Se una sfera di cristallo potesse dirti la verità su te stesso, sulla tua vita, sul futuro o su qualsiasi altra cosa, cosa chiederesti?",
            ],
        ],
        // 23
        [
            'text_target' => "Qual é a maior conquista da sua vida até agora?",
            'text_en'     => "What is the greatest achievement of your life so far?",
            'translations' => [
                'es' => "¿Cuál es el mayor logro de tu vida hasta ahora?",
                'pt' => "Qual é a maior conquista da sua vida até agora?",
                'de' => "Was ist die größte Errungenschaft deines Lebens bisher?",
                'ru' => "Каково ваше величайшее достижение в жизни на сегодняшний день?",
                'ja' => "今のところ、人生で一番誇りに思う達成って何？",
                'zh' => "到目前为止，你人生中最大的成就是什么？",
                'id' => "Apa pencapaian terbesar dalam hidupmu sejauh ini?",
                'it' => "Qual è il più grande traguardo della tua vita fino ad ora?",
            ],
        ],
        // 24
        [
            'text_target' => "O que você mais valoriza em um amigo?",
            'text_en'     => "What do you value most in a friend?",
            'translations' => [
                'es' => "¿Qué es lo que más valoras en un amigo?",
                'pt' => "O que você mais valoriza em um amigo?",
                'de' => "Was schätzt du an einem Freund am meisten?",
                'ru' => "Что вы больше всего цените в друге?",
                'ja' => "友達に一番大切にしていることって何？",
                'zh' => "你最看重朋友身上的什么品质？",
                'id' => "Apa yang paling kamu hargai dari seorang teman?",
                'it' => "Cosa apprezzi di più in un amico?",
            ],
        ],
        // 25
        [
            'text_target' => "Qual é a memória que você mais valoriza?",
            'text_en'     => "What is your most treasured memory?",
            'translations' => [
                'es' => "¿Cuál es tu recuerdo más preciado?",
                'pt' => "Qual é a memória que você mais valoriza?",
                'de' => "Was ist deine wertvollste Erinnerung?",
                'ru' => "Какое воспоминание вы цените больше всего?",
                'ja' => "あなたにとって、一番大切な思い出って何？",
                'zh' => "你最珍视的记忆是什么？",
                'id' => "Apa kenangan yang paling kamu hargai?",
                'it' => "Qual è il tuo ricordo più prezioso?",
            ],
        ],
        // 26
        [
            'text_target' => "Se a sua casa estivesse pegando fogo e você tivesse tempo de salvar apenas um único objeto depois que seus entes queridos e animais de estimação estivessem em segurança, o que você pegaria e por quê?",
            'text_en'     => "If your house was on fire and you had time to save just one single item after your loved ones and pets were safe, what would you grab and why?",
            'translations' => [
                'es' => "Si tu casa estuviera en llamas y tuvieras tiempo de salvar un solo objeto después de que tus seres queridos y mascotas estuvieran a salvo, ¿qué agarrarías y por qué?",
                'pt' => "Se a sua casa estivesse pegando fogo e você tivesse tempo de salvar apenas um único objeto depois que seus entes queridos e animais de estimação estivessem em segurança, o que você pegaria e por quê?",
                'de' => "Wenn dein Haus brennen würde und du nach deinen Liebsten und Haustieren Zeit hättest, nur einen einzigen Gegenstand zu retten, was würdest du nehmen und warum?",
                'ru' => "Если бы ваш дом горел и у вас было время спасти только один предмет после того, как ваши близкие и питомцы в безопасности, что бы вы взяли и почему?",
                'ja' => "家が火事になって、家族やペットが無事な状態で、一つだけ物を持ち出せるとしたら何を持っていく？その理由は？",
                'zh' => "如果你家着火了，家人和宠物都已经安全，你只有时间拿走一样东西，你会拿什么？为什么？",
                'id' => "Kalau rumahmu terbakar dan kamu punya waktu untuk menyelamatkan hanya satu benda setelah orang-orang tersayangmu dan hewan peliharaanmu aman, apa yang kamu ambil dan kenapa?",
                'it' => "Se la tua casa fosse in fiamme e avessi il tempo di salvare un solo oggetto dopo che i tuoi cari e i tuoi animali domestici fossero al sicuro, cosa prenderesti e perché?",
            ],
        ],
    ];

    $insertQ = $conn->prepare(
        "INSERT INTO community_welcome_questions (text_target, text_en, ativo) VALUES (?, ?, 1)"
    );
    $insertT = $conn->prepare(
        "INSERT INTO community_welcome_translations (entity_type, entity_id, lang_code, text)
         VALUES ('question', ?, ?, ?)
         ON DUPLICATE KEY UPDATE text = VALUES(text)"
    );

    $totalQ = 0;
    $totalT = 0;

    foreach ($new_questions as $i => $q) {
        $insertQ->execute([$q['text_target'], $q['text_en']]);
        $newId = $conn->lastInsertId();
        $totalQ++;

        echo "[" . $totalQ . "/26] ID=$newId: " . mb_substr($q['text_en'], 0, 60) . "...\n";

        foreach ($q['translations'] as $lang => $text) {
            $insertT->execute([$newId, $lang, $text]);
            $totalT++;
        }
        echo "      └── " . count($q['translations']) . " traduções inseridas.\n";
    }

    echo "\n=== Concluído! ===\n";
    echo "✅ Perguntas inseridas: $totalQ\n";
    echo "✅ Traduções inseridas/atualizadas: $totalT\n";

    $total = $conn->query("SELECT COUNT(*) FROM community_welcome_questions WHERE ativo = 1")->fetchColumn();
    echo "\n📊 Total de perguntas ativas no banco agora: $total\n";

} catch (Exception $e) {
    echo "\n❌ Erro na migração: " . $e->getMessage() . "\n";
}
