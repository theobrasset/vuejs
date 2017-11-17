<?php
	/**
	 * Classe permetant la gestion de template.
	 * Brosseau Valentin 2017
	 * @version 5
	 * @package Template
	 */
	class Template
	{

		/**
		 * Numero de version de la librairie
		 * @access private
		 */
		var $version = "4.5";

		/**
		 * Activation ou non du mode debug.
		 * @access private
		 */
		var $debug_mode;

		var $classname = "Template";

		/**
		 * Contient le template chargé en mémoire
		 * @access private
		 */
		var $fichiers_templates  = array();

		/**
		 * Contient les variables chargé en mémoire
		 * @access private
		 */
		var $vars_templates  = array();

		/**
		 * Contient les blocks chargé en mémoire
		 * @access private
		 */
		var $vars_blocks = array();

		/**
		 * Variable pour activation de xcache.
		 */
		var $xcacheEnabled = false;

		/**
		 * Constructeur de la classe.
		 * @param bool $debug_mode
		 */
		function Template($debug_mode = 0)
		{
			$this->debug_mode=$debug_mode;

			if (extension_loaded("xcache"))
			{
				$this->xcacheEnabled = true;
			}
		}

		/**
		 * Retourne la version de la librairie de template.
		 * @return String Version de la librairie.
		 */
		function get_version()
		{
			return($this->version);
		}

		/**
		 * méthode set_file, permet de charger un template depuis un fichiers sur disque
		 * @param String $nom_template Nom du template.
		 * @param String $emplacement_template Emplacement du template sur disque
		 */
		function set_file($nom_template, $emplacement_template,$ttl=86400)
		{
			// Template mis en mémoire ou non?
			if($this->xcacheEnabled)
			{
				// Xcache Actif
				//$this->debug_message('xCache Actif.');

				// Nom de la variable en memoire dans Xcache.
				$name = $_SERVER['SERVER_NAME'].'_'.$emplacement_template;

				// Le template est-il présent dans le cache?
				if (xcache_isset($name))
				{
					// Oui
					$this->fichiers_templates[$nom_template] = xcache_get($name);
				}
				else
				{
					// Non
					if(is_file($emplacement_template))
					{
						$this->fichiers_templates[$nom_template] = file_get_contents($emplacement_template);

						// On met en cache le template. pour une durée de $ttl
						xcache_set($name,$this->fichiers_templates[$nom_template],$ttl);
					}
					else
					{
						//$this->debug_message('Erreur: <b>'.$emplacement_template.'</b>  Introuvable.');
						AFLogger::log('Erreur: <b>'.$emplacement_template.'</b>  Introuvable.', AFL_TYPE_ERROR);
					}

				}

			}
			else
			{
				// Xcache desactive
				//$this->debug_message('xCache inactif.');
				// Si le fichier de template exsiste
				if(is_file($emplacement_template))
				{
					$this->fichiers_templates[$nom_template]=file_get_contents($emplacement_template);
				}
				else
				{
					//$this->debug_message('Erreur: <b>'.$emplacement_template.'</b>  Introuvable.');
					AFLogger::log('Erreur: <b>'.$emplacement_template.'</b>  Introuvable.', AFL_TYPE_ERROR);
				}
			}
		}

		/**
		 * Permet de charger un template depuis une variable
		 * @param String $nom_template Nom du template
		 * @param String $template Contenu du template
		 */
		function set_template($nom_template, $template,$id,$ttl=86400)
		{
            $this->fichiers_templates[$nom_template]=$template;
		}



		/**
		 * Assigne une valeur a une variable.
		 * @param String 	  $nom_variable Nom de la variable.
		 * @param String 	  $valeur	Valeur de la variable.
		 * @param Boolean   $concat Concatenation du contenu.
		 * @param Boolean	  $raw affecte la valeur de la variable sans y toucher.
		 */
		function set_var($nom_variable, $valeur = '', $concat='0', $raw=false)
		{
			if($raw)
			{
				// --> Mode RAW assignation brut de la variable.
				$this->vars_templates[$nom_variable] = $valeur;
			}
			else
			{
				if (is_string($nom_variable))
				{
					if(!$concat || !isset($this->vars_templates[$nom_variable]))
					{
						$this->vars_templates[$nom_variable] = $valeur;
					}
					else
					{
						$this->vars_templates[$nom_variable] .= $valeur;
					}
				}
				else if(is_array($nom_variable))
				{
					//Association de tableau. Pour mise un set var depuis un tableau.
					$this->vars_templates = $nom_variable + $this->vars_templates;
				}
				else if(is_object($nom_variable))
				{
					foreach($nom_variable as $key => $value)
						$this->vars_templates[$key] = $value;
				}
				else
				{
					//AFLogger::log('Type inconnu', AFL_TYPE_ERROR);
				}
			}
		}

		/**
		 * Retourne le contenu de la variable.
		 * @param String $nom_variable Nom de la variable.
		 * @return String Contenu de la variable.
		 */
		function get_var($nom_variable)
		{
			if (isset($this->vars_templates[$nom_variable]))
			{
				return ($this->vars_templates[$nom_variable]);
			}
			else{
				$liste = explode(".", $nom_variable);
				if(sizeof($liste)==2){
					return $this->vars_templates[$liste[0]][$liste[1]];
				}elseif(sizeof($liste)==3){
					return $this->vars_templates[$liste[0]][$liste[1]][$liste[2]];
				}else{
					return '';
				}
			}

			if ($this->debug_mode)
			{
				return '{variable inconnu "'.$nom_variable.'"}';
			}

			return '';
		}

		/**
		 * Parse le contenu, CAD remplace les variables a l'interieur du template.
		 * @param String $nom_template Nom du template
		 * @param String $nomvar Nom de la variable ou injecter le retour du parse.
		 */
		function parse($nom_template,$nomvar='')
		{

			$chaine = $this->fichiers_templates[$nom_template];

			reset($this->vars_templates);

			// Gestion des foreach.
			$chaine = preg_replace_callback('/<!-- FOREACH (.*?) -->(.*?)<!-- ENDEACH -->/sm',function ($matches){
				return $this->foreachMeth($matches[1],$matches[2],$nom_template);
			},$chaine);

			// Gestion de la traduction
			$chaine = preg_replace_callback("/{translate\(([^ \t\r\n}]+)\)}/",function ($matches){
				return $this->get_translate($matches[1]);
			},$chaine);

			// Evalue la chaine, execute la fonction php a l'interieur
			$chaine = preg_replace_callback("/{([^ \t\r\n}{]+)\(([^ \t\r\n}{]+)\)}/",function ($matches){
				return $matches[1]($this->get_var($matches[2]));
			},$chaine);

			// equivalent a une boucle while qui passe toute les variables et clef d'un tableau while(list($k, $v) = each($this->vars_templates)) et les remplaces
			$chaine = preg_replace_callback("/{([^ \t\r\n}]+)}/",function ($matches){
				return $this->get_var($matches[1]);
			},$chaine);

			// Gestion des blocks conditionnels
			$chaine = preg_replace_callback('/<!-- IF (.*?) -->(.*?)<!-- FI -->/sm',function ($matches){
				return $this->verif($matches[1],$matches[2]);
			},$chaine);


			// On reinjecte apres avoir remplacer toute les variables
			$this->fichiers_templates[$nom_template] = $chaine;

			// si $nomvar est definie, on reinjecte le template parser dans la variable $nomvar.
			if	($nomvar!='')
			{
				$this->set_var($nomvar,$chaine);
			}
		}

		/**
		 * ==> Ajouter AP 13-03-2014
		 *
		 * Retourne le contenu parser.
		 * @param string $nom_template
		 */
		function get_parse($nom_template){
			$this->parse ($nom_template);
			return $this->fichiers_templates [$nom_template];
		}

		/**
		 * ==> Ajout VBR 11-10-2011
		 * Permet de simuler le foreach dans un template.
		 * @param String $var
		 * @param String $content
		 */
		function foreachMeth($varName,$content,$nom_template)
		{
			$this->vars_blocks[$varName."B"] = $content;
			$this->reset_block($varName."B");

			$i = 0;
			foreach ($this->get_var($varName) as $currentkey=>$current)
			{
				$this->set_var($current);
				$this->set_var("odd",$i%2);
				$this->set_var("builtin_loop_indice",$i++);
				$this->set_var("currentEach",$current);
				$this->set_var("currentEachKey",$currentkey);
				$this->add_block($varName."B");
			}

			return "{".$varName."B}";
		}

//**************************************************************//
//	DEBUT - GESTION BLOCK										//
//**************************************************************//

		/**
		 * Declaration dun blocks
		 * @param String $nom_template nom du template.
		 * @param String $nom_block Nom du block dans le template.
		 */
		function set_block($nom_template,$nom_block)
		{
			$chaine = $this->fichiers_templates[$nom_template];
			// recuperation du contenu du block
			$in=explode('<!-- BEGIN '.$nom_block.' -->', $chaine);
			$in_2=explode('<!-- END '.$nom_block.' -->', $in[1]);
			$this->vars_blocks[$nom_block] = $in_2[0];
			$this->fichiers_templates[$nom_template] = $in[0].'{'.$nom_block.'}'.$in_2[1];
		}

		/**
		 * Retourne le contenu du block specifie
		 * @param String $nom_block Nom du block
		 * @return String Contenu du block demandé
		 */
		function get_block($nom_block)
		{
			return($this->vars_blocks[$nom_block]);
		}

		/**
		 * Remet � zero dans la librairie le block specifie
		 * @param String $nom_block nom du block.
		 */
		function reset_block($nom_block)
		{
			$this->set_var($nom_block,'');
		}

		/**
		 * Ajoute le block
		 * @param String $nom_block nom du block.
		 * @param bool $efface Efface le block apres l'avoir ajoute
		 */
		function add_block($nom_block,$efface =false )
		{
			if ($efface)
			{
				$this->set_var($nom_block,$this->vars_blocks[$nom_block]);
			}
			else
			{
				$this->set_var($nom_block,$this->vars_blocks[$nom_block],'1');
			}

			// Mise a jour des variables a l'interrieur du block.
			reset($this->vars_templates);

			// On recupere le contenu du block
			$chaine = $this->get_var($nom_block);

			// Gestion de la traduction
			$chaine = preg_replace_callback("/{translate\(([^ \t\r\n}]+)\)}/",function ($matches){
				return $this->get_translate($matches[1]);
			},$chaine);

			// Evalue la chaine, et execute les ou la fonction php a l'interieur.
			$chaine = preg_replace_callback("/{([^ \t\r\n}{]+)\(([^ \t\r\n}{]+)\)}/",function ($matches){
				return $matches[1]($this->get_var($matches[2]));
			},$chaine);

			// On remplace les variable correspondante au block en cours, par leur valeur // equivalent a un boucle while qui passe toute les variable et clef d'un tableau while(list($k, $v) = each($this->vars_templates)) et les remplaces
			$chaine = preg_replace_callback("/{([^ \t\r\n}]+)}/",function ($matches){
				return $this->get_var($matches[1]);
			},$chaine);

			// On envoi la nouvelle chaine dans le block.
			$this->set_var($nom_block,$chaine);
		}

		/**
		 * Transforme l'array passé en parametre en un ensemble de block.
		 * @param Array $array
		 * @param String $nomblock
		 * @param $nomtemplate Nom du template.
		 */
		function array_to_block($array,$nomblock,$nomtemplate='')
		{
			//Si le block n'exsiste pas on le créer. // Block existant si il existe une clef dans l'array correpondant a $nomblock.
			if(!isset($this->vars_blocks[$nomblock]))
			{
				if($nomtemplate!='')
				{
					$this->set_block($nomtemplate,$nomblock);
				}
				else
				{
					AFLogger::log('Votre block &eacute;tait inexistant, si vous souhaiter le cr&eacute;er il faut aussi passer le nom du template en cours.', AFL_TYPE_ERROR);
					//$this->debug_message('Votre block &eacute;tait inexistant, si vous souhaiter le cr&eacute;er il faut aussi passer le nom du template en cours.');
				}
			}
			// Si c'est un tableau on va le parcourir.
			if(is_array($array))
			{
				$this->reset_block($nomblock);
				/*for($j = 0; $j < count($array); $j++)
                {
                    $this->set_var($array[$j]);
                    $this->add_block($nomblock);
                }*/
				foreach($array as $value)
				{
					$this->set_var($value);
					$this->add_block($nomblock);
				}
			}
			else
			{
				//$this->debug_message($array.': Doit etre un tableau.');
				//AFLogger::log($array.': Doit etre un tableau.', AFL_TYPE_ERROR);
			}
		}

//******************************************************************//
//	FIN - GESTION BLOCK											    //
//******************************************************************//

		/**
		 * Gestion des blocks conditionnel
		 * @param String $nom_template
		 */
		function blockcon($nom_template)
		{
			// On recupere le contenu du template
			$chaine = $this->fichiers_templates[$nom_template];
			// On remplace les blocks conditionnelle par leur valeur UNIQUEMENT si la condition est remplis
			// '(\1)?\'\2\':"";' est équivalent à if(true) return 'aaa'; else return '';
			$this->fichiers_templates[$nom_template] = preg_replace_callback('/<!-- IF (.*) -->(.*)<!-- FI -->/m',function ($matches) {
				return $this->verif($matches[1],$matches[2]);
			},$chaine);

		}

		function verif($if,$res)
		{
			return eval('if('.stripslashes($if).') return stripslashes($res); else return "";');
		}


		/**
		 * Parse le template et le sort sur la sortie standard.
		 * @param String $nom_template
		 */
		function pparse($nom_template)
		{
			$this->parse($nom_template);
			echo $this->fichiers_templates[$nom_template];
		}


		/**
		 * Affichage des messages lors de l'utilisation en mode debug.
		 * @param String $message
		 */
		function debug_message($message)
		{ // normalement, on utilise AFLogger::log()
			if ($this->debug_mode === 1)
			{
				echo '<div style=\"color:red\">'.$message.'</div>';
			}
		}

    /**
     * Gestion de la traduction
     */
    function get_translate($etiquette){
        return get_translate($etiquette);
    }
	}

?>
